import express from "express";
import bodyParser from "body-parser";
import escpos from "escpos";
import { randomBytes } from "crypto";
import { printTicket } from "./src/lib/printing/printEscposTicket.js";

// Ensure ESC/POS adapters exist for both USB and network printers.
escpos.USB = escpos.USB || escpos.Adapter?.USB;
escpos.Network = escpos.Network || escpos.Adapter?.Network;

const app = express();
const port = Number(process.env.PRINT_SERVER_PORT) || 4000;

app.use(bodyParser.json({ limit: "1mb" }));

function toNumberOrUndefined(value) {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : undefined;
}

function buildPrinterDevice() {
  const networkHost = process.env.PRINTER_NETWORK_HOST;
  const networkPort =
    toNumberOrUndefined(process.env.PRINTER_NETWORK_PORT) || 9100;

  if (networkHost) {
    if (!escpos.Network) {
      throw new Error("El adaptador de red de escpos no está disponible");
    }

    return new escpos.Network(networkHost, networkPort);
  }

  const vendorId = toNumberOrUndefined(process.env.PRINTER_VENDOR_ID);
  const productId = toNumberOrUndefined(process.env.PRINTER_PRODUCT_ID);

  if (!escpos.USB) {
    throw new Error("El adaptador USB de escpos no está disponible");
  }

  return vendorId && productId
    ? new escpos.USB(vendorId, productId)
    : new escpos.USB();
}

function buildControlCode(preferredCode) {
  if (preferredCode) return preferredCode;
  const randomSuffix = randomBytes(3).toString("hex");
  return `CTRL-${randomSuffix}`.toUpperCase();
}

app.post("/imprimir", async (req, res) => {
  const { tipo, id, fecha, hora, tragoGratis, nota, controlCode } =
    req.body || {};

  if (!tipo || !fecha || !hora) {
    return res.status(400).json({
      message:
        "Faltan campos obligatorios para imprimir el ticket (tipo, fecha, hora)",
    });
  }

  const payload = {
    tipo,
    id,
    fecha,
    hora,
    tragoGratis: tragoGratis ?? undefined,
    nota,
  };

  try {
    const device = buildPrinterDevice();
    await printTicket(payload, buildControlCode(controlCode), device);

    return res.json({
      message: "Ticket enviado a la impresora",
      payload,
    });
  } catch (error) {
    console.error("No se pudo imprimir el ticket:", error);
    return res.status(500).json({
      message: "No se pudo imprimir el ticket",
      error: error.message,
    });
  }
});

app.get("/salud", (_req, res) => {
  res.json({ status: "ok", printer: Boolean(escpos.USB || escpos.Network) });
});

if (import.meta.url === `file://${process.argv[1]}`) {
  app.listen(port, () => {
    console.log(`Servidor de impresión escuchando en http://localhost:${port}`);
  });
}

export default app;
