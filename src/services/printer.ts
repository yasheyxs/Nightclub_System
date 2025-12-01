/* eslint-disable @typescript-eslint/no-explicit-any */
export interface TicketPayload {
  evento?: string;
  entrada: string;
  cantidad: number;
  incluyeTrago: boolean;
}

type QZTray = {
  websocket: {
    setClosedCallbacks: any;
    setErrorCallbacks: any;
    isActive: () => boolean;
    connect: (config?: {
      host?: string;
      port?: number;
      keepAlive?: boolean;
      secure?: boolean;
    }) => Promise<void>;
  };
  security: {
    setCertificatePromise: (
      promise: (
        resolve: (certificate?: string | null) => void,
        reject?: (reason?: unknown) => void
      ) => void
    ) => void;
    setSignaturePromise: (
      promise: (
        toSign: unknown,
        resolve: (signature: string | null) => void,
        reject?: (reason?: unknown) => void
      ) => void
    ) => void;
  };
  printers: {
    find: (printerName: string) => Promise<string>;
  };
  configs: {
    create: (
      printer: string,
      options?: Record<string, string | number | boolean>
    ) => unknown;
  };
  print: (config: unknown, data: Array<string | Record<string, unknown>>) => Promise<void>;
};

declare global {
  interface Window {
    qz?: QZTray;
  }
}

const PRINTER_NAME = "Xprinter XPE200L";
const QZ_ENCODING = "CP1252";

const getQz = (): QZTray => {
  const qz = window.qz;
  if (!qz) {
    throw new Error("QZ Tray no está disponible. Verificá que el cliente esté abierto.");
  }
  return qz;
};

const ensureConnection = async () => {
  const qz = getQz();

  if (!qz.websocket.isActive()) {
    qz.security.setCertificatePromise((resolve) => resolve(null));
    qz.security.setSignaturePromise((toSign, resolve) => resolve(null));

    await qz.websocket.connect({
      host: "localhost",
      keepAlive: true,
      secure: false,
    });
  }

  return qz;
};

export const imprimirTicket = async (payload: TicketPayload) => {
  const qz = await ensureConnection();

  const printer = await qz.printers
    .find(PRINTER_NAME)
    .catch(() => PRINTER_NAME);

  const config = qz.configs.create(printer, {
    encoding: QZ_ENCODING,
    altPrinting: true,
  });

  const fechaActual = new Date();
  const lineas = [
    "SANTAS CLUB",
    payload.evento ? `Evento: ${payload.evento}` : "Evento: Sin nombre",
    `Entrada: ${payload.entrada}`,
    `Cantidad: ${payload.cantidad}`,
    `Trago: ${payload.incluyeTrago ? "Sí" : "No"}`,
    `Fecha: ${fechaActual.toLocaleDateString("es-AR")} ${fechaActual.toLocaleTimeString("es-AR")}`,
    "",
  ];

  const data = [
    { type: "raw", format: "command", data: "\x1B@\n" },
    lineas.join("\n") + "\n",
    { type: "raw", format: "command", data: "\x1B\x64\x03" },
    { type: "raw", format: "command", data: "\x1DVA\0" },
  ];

  await qz.print(config, data);
};