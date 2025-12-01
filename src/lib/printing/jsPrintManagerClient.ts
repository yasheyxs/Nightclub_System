const JSPM_SCRIPT_URL = "https://jsprintmanager.azurewebsites.net/scripts/JSPrintManager.js";

const SELECTED_PRINTER_KEY = "jspm:selectedPrinter";
const PRINTER_CACHE_KEY = "jspm:cachedPrinters";

const statusSubscribers = new Set<(status: number) => void>();
let scriptPromise: Promise<unknown> | null = null;
let connectionPromise: Promise<unknown> | null = null;

export type TicketPayload = {
  id: string;
  fecha: string;
  hora: string;
  tipo: string;
  controlCode?: string;
  nota?: string;
  detalle?: string;
  [key: string]: string | undefined;
};

export type TicketImage = {
  base64: string;
  fileName?: string;
};

export type PrintOptions = {
  printerName?: string;
  images?: TicketImage[];
};

interface JSPrintManagerInterface {
  websocket_status: number;
  WSStatus: {
    Open: number;
    Closed: number;
    Blocked: number;
  };
  start: () => void;
  auto_reconnect: boolean;
  getPrinters: () => Promise<string[]>;
  onStatusChanged: (() => void) | undefined;
  ClientPrintJob: {
    new (): {
      clientPrinter?: unknown;
      files: unknown[];
      sendToClient?: () => Promise<void> | void;
    };
  };
  InstalledPrinter: {
    new (printerName: string): unknown;
  };
  PrintFileTXT: {
    new (
      text: string,
      sourceType: unknown,
      fileName: string,
      printCount?: number,
    ): unknown;
  };
  PrintFilePNG: {
    new (
      base64: string,
      sourceType: unknown,
      fileName: string,
      printCount?: number,
    ): unknown;
  };
  FileSourceType: {
    Text: number | string;
    Base64: number | string;
    [key: string]: unknown;
  };
}

interface JSPMInterface {
  JSPrintManager?: JSPrintManagerInterface;
}

function isBrowser(): boolean {
  return typeof window !== "undefined" && typeof document !== "undefined";
}

function assertBrowser(): void {
  if (!isBrowser()) {
    throw new Error("jsPrintManager solo está disponible en el navegador.");
  }
}

function notifyStatus(status: number): void {
  statusSubscribers.forEach((listener) => {
    listener(status);
  });
}

export function onPrintServiceStatusChange(
  listener: (status: number) => void,
): () => void {
  statusSubscribers.add(listener);
  return () => statusSubscribers.delete(listener);
}

async function loadJspmLibrary(): Promise<JSPMInterface> {
  assertBrowser();

  const jspm = (window as unknown as { JSPM?: JSPMInterface }).JSPM;
  if (jspm && jspm.JSPrintManager) {
    return jspm;
  }

  if (!scriptPromise) {
    scriptPromise = new Promise((resolve, reject) => {
      const script = document.createElement("script");
      script.src = JSPM_SCRIPT_URL;
      script.async = true;

      script.onload = () => {
        const jspm = (window as unknown as { JSPM?: JSPMInterface }).JSPM;
        if (!jspm || !jspm.JSPrintManager) {
          reject(new Error("No se pudo cargar jsPrintManager. El objeto JSPrintManager no está disponible."));
          return;
        }

        resolve(jspm);
      };

      script.onerror = () => {
        reject(
          new Error("No se pudo descargar jsPrintManager. Verifica tu conexión a internet."),
        );
      };

      document.head.appendChild(script);
    });
  }

  return scriptPromise;
}


function handleStatusHooks(jspm: JSPMInterface): void {
  const onStatusChange = () => {
    if (jspm.JSPrintManager) {
      const status = jspm.JSPrintManager.websocket_status;

      if (status === jspm.JSPrintManager.WSStatus.Closed) {
        connectionPromise = null;
      }

      notifyStatus(status);
    }
  };

  if (jspm.JSPrintManager) {
    jspm.JSPrintManager.onStatusChanged = onStatusChange;
  }
}

async function ensureConnected(): Promise<JSPMInterface> {
  const jspm = await loadJspmLibrary();

  if (!jspm.JSPrintManager) {
    throw new Error("jsPrintManager no está disponible. Asegúrate de que el script se haya cargado correctamente.");
  }

  // Verifica si la conexión está abierta antes de continuar
  if (jspm.JSPrintManager.websocket_status === jspm.JSPrintManager.WSStatus.Open) {
    return jspm;
  }

  if (!connectionPromise) {
    jspm.JSPrintManager.auto_reconnect = true;
    jspm.JSPrintManager.start();

    connectionPromise = new Promise((resolve, reject) => {
      handleStatusHooks(jspm);

      const checkStatus = () => {
        if (jspm.JSPrintManager) {
          const status = jspm.JSPrintManager.websocket_status;

          if (status === jspm.JSPrintManager.WSStatus.Open) {
            resolve(jspm);
            return;
          }

          if (status === jspm.JSPrintManager.WSStatus.Blocked) {
            reject(new Error("El navegador bloqueó la conexión con el servicio de impresión."));
          }
        }
      };

      checkStatus();
      const interval = window.setInterval(() => {
        checkStatus();
      }, 350);

      connectionPromise?.finally(() => window.clearInterval(interval));
    });
  }

  return connectionPromise;
}


export async function initializePrintService(): Promise<void> {
  await ensureConnected();
}

export async function isPrintServiceConnected(): Promise<boolean> {
  const jspm = await loadJspmLibrary();
  return jspm.JSPrintManager && jspm.JSPrintManager.websocket_status === jspm.JSPrintManager.WSStatus.Open;
}

function getLocalStorage(): Storage {
  assertBrowser();
  return window.localStorage;
}

export function getSavedPrinter(): string | null {
  const storage = getLocalStorage();
  return storage.getItem(SELECTED_PRINTER_KEY);
}

export function saveSelectedPrinter(printerName: string): void {
  const storage = getLocalStorage();
  storage.setItem(SELECTED_PRINTER_KEY, printerName);
}

export async function getPrintersOnce(forceRefresh = false): Promise<string[]> {
  const storage = getLocalStorage();

  if (!forceRefresh) {
    const cached = storage.getItem(PRINTER_CACHE_KEY);
    if (cached) {
      return JSON.parse(cached);
    }
  }

  const jspm = await ensureConnected();
  const printers: string[] = await jspm.JSPrintManager.getPrinters();

  storage.setItem(PRINTER_CACHE_KEY, JSON.stringify(printers));
  return printers;
}

function buildTicketBody(payload: TicketPayload): string {
  const lines = [
    "*** TICKET ***",
    `Tipo: ${payload.tipo}`,
    `ID: ${payload.id}`,
    `Fecha: ${payload.fecha}`,
    `Hora: ${payload.hora}`,
  ];

  if (payload.controlCode) {
    lines.push(`Control: ${payload.controlCode}`);
  }

  if (payload.detalle) {
    lines.push(`Detalle: ${payload.detalle}`);
  }

  if (payload.nota) {
    lines.push(`Nota: ${payload.nota}`);
  }

  const extras = Object.entries(payload).filter(([key]) => {
    return ![
      "id",
      "fecha",
      "hora",
      "tipo",
      "controlCode",
      "nota",
      "detalle",
    ].includes(key);
  });

  extras.forEach(([key, value]) => {
    if (value) {
      lines.push(`${key}: ${value}`);
    }
  });

  lines.push("", "Gracias por su compra", "----------------------");

  return lines.join("\n");
}

export async function printTicket(
  payload: TicketPayload,
  options: PrintOptions = {},
): Promise<void> {
  const jspm = await ensureConnected();

  const printerName = options.printerName || getSavedPrinter();
  if (!printerName) {
    throw new Error(
      "No hay una impresora guardada. Selecciona una impresora antes de imprimir.",
    );
  }

  const status = jspm.JSPrintManager.websocket_status;
  if (status !== jspm.JSPrintManager.WSStatus.Open) {
    throw new Error(
      "La conexión con el servicio de impresión no está activa en este momento.",
    );
  }

  const job = new jspm.JSPrintManager.ClientPrintJob();
  job.clientPrinter = new jspm.JSPrintManager.InstalledPrinter(printerName);

  const ticketBody = buildTicketBody(payload);
  const ticketFile = new jspm.JSPrintManager.PrintFileTXT(
    ticketBody,
    jspm.JSPrintManager.FileSourceType.Text,
    "ticket.txt",
    1,
  );

  job.files.push(ticketFile);

  if (options.images?.length) {
    options.images.forEach((image, index) => {
      const fileName = image.fileName || `ticket-image-${index + 1}.png`;
      const imageFile = new jspm.JSPrintManager.PrintFilePNG(
        image.base64,
        jspm.JSPrintManager.FileSourceType.Base64,
        fileName,
        1,
      );

      job.files.push(imageFile);
    });
  }

  await job.sendToClient();
}

export async function rememberPrinterAndPrint(
  payload: TicketPayload,
  printerName: string,
  options: Omit<PrintOptions, "printerName"> = {},
): Promise<void> {
  saveSelectedPrinter(printerName);
  await printTicket(payload, { ...options, printerName });
}
