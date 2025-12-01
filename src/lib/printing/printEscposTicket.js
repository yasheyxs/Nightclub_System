import escpos from "escpos";

// Ensure ESC/POS adapters are available (alpha versions of escpos expose them differently).

escpos.USB = escpos.USB || escpos.Adapter?.USB;
escpos.Network = escpos.Network || escpos.Adapter?.Network;

/**
 * Prints a thermal ticket for the SANTAS event using an ESC/POS-compatible USB printer.
 *
 * @param {{
 *  tipo: string;
 *  id?: string;
 *  fecha: string;
 *  hora: string;
 *  tragoGratis?: boolean;
 *  nota?: string;
 * }} payload - Ticket payload with dynamic values.
 * @param {string} controlCode - Control code to include on the ticket.
 * @param {escpos.USB | escpos.Network} [deviceOverride] - Optional pre-configured printer device instance.
 * @returns {Promise<void>} Resolves when the print job is sent.
 */
export async function printTicket(payload, controlCode, deviceOverride) {
  const device = deviceOverride || new escpos.USB();
  const printer = new escpos.Printer(device, { width: 80 });

  return new Promise((resolve, reject) => {
    device.open((error) => {
      if (error) {
        reject(error);
        return;
      }

      try {
        const detailLines = [
          `Tipo: ${payload.tipo}`,
          payload.id ? `ID: ${payload.id}` : null,
          `Fecha: ${payload.fecha}`,
          `Hora: ${payload.hora}`,
          payload.tragoGratis === undefined
            ? null
            : `Trago gratis: ${payload.tragoGratis ? "SI" : "NO"}`,
          payload.nota ? `Nota: ${payload.nota}` : null,
        ].filter(Boolean);

        printer
          .align("CT")
          .style("B")
          .size(1, 1)
          .text("SANTAS")
          .size(0, 0)
          .style("NORMAL")
          .align("LT")
          .text("ENTRADA DIGITAL")
          .text("---------------------------");

        detailLines.forEach((line) => printer.text(line));

        printer
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
