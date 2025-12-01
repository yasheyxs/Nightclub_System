export const initQZConnection = async (): Promise<void> => {
  if (typeof window === "undefined") {
    console.error("QZ Tray no está disponible: no estás en un navegador.");
    return;
  }

  const qz = window.qz;

  if (!qz) {
    console.error("QZ Tray no está cargado en window.qz");
    return;
  }

  // Si ya está conectado, no lo hagas de nuevo
  if (qz.websocket?.isActive()) {
    console.log("QZ Tray ya estaba conectado.");
    return;
  }

  try {
    console.log("Intentando conectar a QZ Tray...");

    await qz.websocket.connect();

    console.log("Conexión con QZ Tray establecida.");

    // Listeners (solo si existen)
    if (qz.websocket.setClosedCallbacks) {
      qz.websocket.setClosedCallbacks(() => {
        console.warn("QZ Tray se desconectó. Reintentando...");
        setTimeout(() => initQZConnection(), 3000);
      });
    }

    if (qz.websocket.setErrorCallbacks) {
      qz.websocket.setErrorCallbacks((err: unknown) => {
        console.error("Error de QZ Tray:", err);
      });
    }

  } catch (err) {
    console.error("Error al conectar a QZ Tray:", err);
  }
};
