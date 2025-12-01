import * as qz from "qz-tray";  // Importa QZ Tray correctamente

// Definición de la interfaz para el ticket
export interface TicketPayload {
  tipo: string;
  id: number | string;
  fecha: string;
  hora: string;
}

const THERMAL_PRINTER = "Xprinter XPE200L";

// Verificar si QZ Tray está cargado
const ensureQzLoaded = (): qz.QZ => {
  if (typeof window === "undefined" || !window.qz) {
    throw new Error("QZ Tray no está disponible. Verificá la instalación.");
  }

  return window.qz;
};

// Conectar a la impresora
export const connectPrinter = async (): Promise<void> => {
  const qz = ensureQzLoaded();

  if (qz.websocket.isActive()) {
    console.log("Conexión WebSocket activa");
    return;
  }

  try {
    await qz.websocket.connect();
    console.log("Conexión WebSocket establecida");
  } catch (error) {
    console.error("Error al conectar a QZ Tray: ", error);
  }
};

// Generar los comandos para imprimir el ticket
const buildTicketCommands = (payload: TicketPayload): string[] => {
  const controlCode = `SC-${payload.id}-${String(Date.now()).slice(-6)}`;

  return [
    "\x1B\x40",  // Inicializa la impresora
    "\x1B\x61\x01",  // Centra el texto
    "\x1B\x45\x01",  // Activa negrita
    "SANTAS\n",  // Nombre del evento
    "\x1B\x45\x00",  // Desactiva negrita
    "ENTRADA DIGITAL\n",  // Tipo de entrada
    "\x1B\x61\x00",  // Alineación izquierda
    "---------------------------\n",  // Separador
    `Tipo: ${payload.tipo}\n`,  // Tipo de ticket
    `Ticket ID: ${payload.id}\n`,  // ID del ticket
    `Fecha: ${payload.fecha}\n`,  // Fecha
    `Hora: ${payload.hora}\n`,  // Hora
    "---------------------------\n",  // Separador
    "NO COMPARTIR ESTE TICKET\n",  // Mensaje
    `Codigo: ${controlCode}\n`,  // Código de control
    "---------------------------\n",  // Separador
    "\x1B\x61\x01",  // Centra el texto
    "Gracias por tu compra\n\n",  // Agradecimiento
    "\x1B\x61\x00",  // Alineación izquierda
    "\x1D\x56\x00",  // Corte de papel
  ];
};

export const printTicket = async (payload: TicketPayload): Promise<void> => {
  const qz = ensureQzLoaded();
  try {
    await connectPrinter();

    const config = qz.configs.create(THERMAL_PRINTER, {
      altPrinting: true,
      encoding: "CP1252",  // Asegura la codificación correcta
    });

    const commands = buildTicketCommands(payload);  // Construye los comandos para el ticket

    await qz.print(config, commands);  // Envía los comandos a la impresora
    console.log("Ticket impreso exitosamente");
  } catch (error) {
    console.error("Error al imprimir el ticket: ", error);
  }
};


