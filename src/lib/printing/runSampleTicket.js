import escpos from "escpos";
import { printTicket } from "./printEscposTicket.js";

const now = new Date();
const fallbackFecha = now.toLocaleDateString("es-ES");
const fallbackHora = now.toLocaleTimeString("es-ES", {
  hour: "2-digit",
  minute: "2-digit",
});

const payload = {
  tipo: process.env.TICKET_TIPO || "GENERAL",
  id: process.env.TICKET_ID || "0000-0000",
  fecha: process.env.TICKET_FECHA || fallbackFecha,
  hora: process.env.TICKET_HORA || fallbackHora,
};

const controlCode = process.env.TICKET_CONTROL_CODE || "CONTROL-0000";

const vendorId = process.env.PRINTER_VENDOR_ID
  ? Number(process.env.PRINTER_VENDOR_ID)
  : undefined;
const productId = process.env.PRINTER_PRODUCT_ID
  ? Number(process.env.PRINTER_PRODUCT_ID)
  : undefined;

const usbDevice =
  vendorId && productId
    ? new escpos.USB(vendorId, productId)
    : new escpos.USB();

async function main() {
  await printTicket(payload, controlCode, usbDevice);
}

if (import.meta.url === `file://${process.argv[1]}`) {
  main().catch((error) => {
    console.error("No se pudo imprimir el ticket de ejemplo:", error);
    process.exitCode = 1;
  });
}

export { main as printSampleTicket };
