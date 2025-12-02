#!/usr/bin/env node

const { existsSync } = require("fs");
const { resolve } = require("path");
const { spawnSync } = require("child_process");

const [printerName, fileArg] = process.argv.slice(2);

if (!printerName || !fileArg) {
  console.error(
    'Uso: node scripts/windows-print.js "Nombre de la impresora" Ruta/Al/Archivo'
  );
  console.error(
    'Ejemplo: node scripts/windows-print.js "Generic / Text Only" ./documento.txt'
  );
  process.exit(1);
}

const filePath = resolve(fileArg);
if (!existsSync(filePath)) {
  console.error(`El archivo no existe: ${filePath}`);
  process.exit(1);
}

const args = ["/c", "print", `/D:"${printerName}"`, `"${filePath}"`];
const result = spawnSync(
  "powershell",
  [
    "-Command",
    `Start-Process -FilePath '${filePath}' -Verb PrintTo -ArgumentList '${printerName}'`,
  ],
  { stdio: "inherit" }
);

if (result.error) {
  console.error(
    `No se pudo ejecutar el comando de impresión: ${result.error.message}`
  );
  process.exit(1);
}

process.exit(result.status ?? 0);
