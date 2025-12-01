import escpos from "escpos";

// Ensure USB adapter is available (alpha versions of escpos expose it differently).
escpos.USB = escpos.USB || escpos.Adapter?.USB;

/**
 * Prints a thermal ticket for the SANTAS event using an ESC/POS-compatible USB printer.
 *
 * @param {{ tipo: string; id: string; fecha: string; hora: string }} payload - Ticket payload with dynamic values.
 * @param {string} controlCode - Control code to include on the ticket.
 * @param {escpos.USB} [usbDevice] - Optional pre-configured USB device instance.
 * @returns {Promise<void>} Resolves when the print job is sent.
 */
export async function printTicket(payload, controlCode, usbDevice) {
  const device = usbDevice || new escpos.USB();
  const printer = new escpos.Printer(device, { width: 80 });

  return new Promise((resolve, reject) => {
    device.open((error) => {
      if (error) {
        reject(error);
        return;
      }

      try {
        printer
          .align("CT")
          .style("B")
          .size(1, 1)
          .text("SANTAS")
          .size(0, 0)
          .style("NORMAL")
          .align("LT")
          .text("ENTRADA DIGITAL")
          .text("---------------------------")
          .text(`Tipo: ${payload.tipo}`)
          .text(`ID: ${payload.id}`)
          .text(`Fecha: ${payload.fecha}`)
          .text(`Hora: ${payload.hora}`)
          .text("---------------------------")
          .text("NO COMPARTIR ESTE TICKET")
          .text(`Control: ${controlCode}`)
          .text("---------------------------")
          .align("CT")
          .text("Gracias por tu compra")
          .cut()
          .close();

        resolve();
      } catch (printError) {
        reject(printError);
      }
    });
  });
}
