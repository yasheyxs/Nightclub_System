// Aseguramos que JSPM esté disponible globalmente en el objeto Window
interface Window {
  JSPM?: unknown; // Cambié 'any' por 'unknown' para mejorar la seguridad de tipos
}

// Declaramos JSPM con un tipo específico, si conoces la estructura de JSPM, sustitúyelo por un tipo más específico
declare const JSPM: unknown; // Cambié 'any' por 'unknown' también aquí

export {};
