const QZ_SCRIPT_PATH = "./qzTray.ts";

interface EntradaTicketPayload {
  eventName: string;
  ticketName: string;
  quantity: number;
  unitPrice: number;
  includeDrink: boolean;
  total: number;
  printer?: string | null;
}

const normalizePem = (pem?: string) =>
  pem?.replace(/\\n/g, "\n").trim() ?? "";

const decodePrivateKey = async (privateKeyPem: string) => {
  const cleaned = privateKeyPem
    .replace("-----BEGIN PRIVATE KEY-----", "")
    .replace("-----END PRIVATE KEY-----", "")
    .replace(/\s+/g, "");

  const binary = Uint8Array.from(atob(cleaned), (char) => char.charCodeAt(0));

  return crypto.subtle.importKey(
    "pkcs8",
    binary.buffer,
    {
      name: "RSASSA-PKCS1-v1_5",
      hash: "SHA-256",
    },
    false,
    ["sign"]
  );
};

const signMessage = async (message: string, privateKeyPem: string) => {
  const key = await decodePrivateKey(privateKeyPem);
  const encoder = new TextEncoder();
  const signature = await crypto.subtle.sign(
    "RSASSA-PKCS1-v1_5",
    key,
    encoder.encode(message)
  );

  return btoa(String.fromCharCode(...new Uint8Array(signature)));
};

const ensureQzLoaded = async () => {
  if (typeof window === "undefined") {
    throw new Error("QZ Tray sólo está disponible en el navegador");
  }

  if (window.qz) {
    return window.qz;
  }

  await new Promise<void>((resolve, reject) => {
    const existingScript = document.querySelector<HTMLScriptElement>(
      `script[data-qz-tray]`
    );

    if (existingScript) {
      existingScript.addEventListener("load", () => resolve());
      existingScript.addEventListener("error", () =>
        reject(new Error("No se pudo cargar qz-tray.js"))
      );
      return;
    }

    const script = document.createElement("script");
    script.src = QZ_SCRIPT_PATH;
    script.async = true;
    script.dataset.qzTray = "true";
    script.onload = () => resolve();
    script.onerror = () =>
      reject(
        new Error(
          `No se pudo cargar qz-tray.js. Verifica que el archivo esté disponible en ${QZ_SCRIPT_PATH}`
        )
      );

    document.body.appendChild(script);
  });

  if (!window.qz) {
    throw new Error(
      "No se detectó QZ Tray. Asegúrate de haber descargado qz-tray.js y tener la app corriendo"
    );
  }

  return window.qz;
};

// eslint-disable-next-line @typescript-eslint/no-explicit-any
const configureSecurity = async (qz: any) => {
  const certificate = normalizePem(import.meta.env.VITE_QZ_CERTIFICATE);
  const privateKey = normalizePem(import.meta.env.VITE_QZ_PRIVATE_KEY);

  if (!certificate) {
    throw new Error(
      "Falta configurar VITE_QZ_CERTIFICATE con el certificado público de QZ Tray"
    );
  }

  if (!privateKey) {
    throw new Error(
      "Falta configurar VITE_QZ_PRIVATE_KEY con la clave privada para firmar"
    );
  }

  qz.security.setCertificatePromise(() => Promise.resolve(certificate));
  qz.security.setSignaturePromise((toSign: string) =>
    signMessage(toSign, privateKey)
  );
};

const ensureQzConnection = async () => {
  const qz = await ensureQzLoaded();
  await configureSecurity(qz);

  if (qz.websocket.isActive()) {
    return qz;
  }

  try {
    await qz.websocket.connect();
    return qz;
  } catch (error) {
    if (
      error instanceof Error &&
      error.message?.toLowerCase().includes("already connected")
    ) {
      return qz;
    }
    throw error;
  }
};

const buildTicketHtml = (payload: EntradaTicketPayload) => {
  const formatter = new Intl.NumberFormat("es-AR", {
    style: "currency",
    currency: "ARS",
    maximumFractionDigits: 0,
  });

  const lines = [
    `<div style="font-family: 'Inter', sans-serif; width: 260px;">`,
    `<h2 style="margin: 0 0 8px; font-size: 16px;">Santas Club</h2>`,
    `<p style="margin: 0 0 4px; font-size: 12px;">Evento: <strong>${payload.eventName}</strong></p>`,
    `<p style="margin: 0 0 8px; font-size: 12px;">Entrada: <strong>${payload.ticketName}</strong></p>`,
    `<hr />`,
    `<p style="margin: 4px 0; font-size: 12px;">Cantidad: ${payload.quantity}</p>`,
    `<p style="margin: 4px 0; font-size: 12px;">Precio unitario: ${formatter.format(payload.unitPrice)}</p>`,
    payload.includeDrink
      ? `<p style="margin: 4px 0; font-size: 12px;">Incluye trago: Sí</p>`
      : `<p style="margin: 4px 0; font-size: 12px;">Incluye trago: No</p>`,
    `<p style="margin: 8px 0; font-size: 13px; font-weight: 600;">Total: ${formatter.format(payload.total)}</p>`,
    `<p style="margin: 12px 0 0; font-size: 11px;">Fecha: ${new Date().toLocaleString("es-AR")}</p>`,
    `</div>`,
  ];

  return lines.join("");
};

export const printEntradaTicket = async (payload: EntradaTicketPayload) => {
  const qz = await ensureQzConnection();
  const printer = payload.printer ?? import.meta.env.VITE_QZ_PRINTER ?? null;

  const config = qz.configs.create(printer, {
    scaleContent: true,
    rasterize: true,
    altPrinting: false,
  });

  const html = buildTicketHtml(payload);

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  await qz.print(config, [{ type: "html", format: "plain", data: html }] as any);
};

export const checkQzStatus = async () => {
  const qz = await ensureQzLoaded();
  return qz.websocket.isActive();
};

export type { EntradaTicketPayload };