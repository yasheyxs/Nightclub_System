<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestedFile = __DIR__ . $uriPath;

if (is_file($requestedFile)) {
    return false;
}

$rawBody = file_get_contents('php://input');
$decodedJson = json_decode($rawBody, true) ?? [];

header("Content-Type: application/json; charset=utf-8");

/* =========================
   API ROUTER
========================= */

if (str_starts_with($uriPath, '/api/')) {
    require __DIR__ . '/api/index.php';
    exit;
}

/* =========================
   PRINT ROUTER
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($uriPath === '/' || $uriPath === '/print')) {
    $printerName = $_POST['printerName'] ?? ($decodedJson['printerName'] ?? null);
    $filePath = $_POST['filePath'] ?? ($decodedJson['filePath'] ?? null);

    if (!$printerName || !$filePath) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Parámetros requeridos: printerName y filePath.'
        ]);
        exit;
    }

    if (!file_exists($filePath) || !is_file($filePath)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'El archivo no existe en la ruta especificada.'
        ]);
        exit;
    }

    $scriptsDir = __DIR__ . '/scripts/';
    $nodeScript = $scriptsDir . 'windows-print.js';
    $psScript = $scriptsDir . 'windows-print.ps1';

    if (is_file($nodeScript)) {
        $command = sprintf(
            'node %s %s %s 2>&1',
            escapeshellarg($nodeScript),
            escapeshellarg($printerName),
            escapeshellarg($filePath)
        );
    } elseif (is_file($psScript)) {
        $command = sprintf(
            'powershell -ExecutionPolicy Bypass -File %s %s %s 2>&1',
            escapeshellarg($psScript),
            escapeshellarg($printerName),
            escapeshellarg($filePath)
        );
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró ningún script de impresión.'
        ]);
        exit;
    }

    exec($command, $output, $exitCode);

    if ($exitCode === 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Trabajo de impresión enviado correctamente.',
            'output' => $output
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error al ejecutar impresión.',
            'output' => $output,
            'exitCode' => $exitCode
        ]);
    }
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Ruta no encontrada']);
