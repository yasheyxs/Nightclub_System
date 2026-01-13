<?php
// router.php — manejador universal con CORS y endpoint de impresión

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=utf-8");

// Responder preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestedFile = __DIR__ . $uriPath;

// Si el archivo solicitado existe, permitir que el servidor embebido lo sirva
if (is_file($requestedFile)) {
    return false;
}

// Normalizar datos de entrada
$rawBody = file_get_contents('php://input');
$decodedJson = json_decode($rawBody, true);
$printerName = $_POST['printerName'] ?? ($decodedJson['printerName'] ?? null);
$filePath = $_POST['filePath'] ?? ($decodedJson['filePath'] ?? null);

// Solo aceptamos POST hacia "/print" (o la raíz como alias)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($uriPath === '/' || $uriPath === '/print')) {
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
            'message' => 'El archivo no existe en la ruta especificada.',
        ]);
        exit;
    }

    $scriptsDir = __DIR__ . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR;
    $nodeScript = $scriptsDir . 'windows-print.js';
    $psScript = $scriptsDir . 'windows-print.ps1';

    $command = null;
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
    }

    if ($command === null) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró ningún script de impresión disponible.',
        ]);
        exit;
    }

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    if ($exitCode === 0) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Trabajo de impresión enviado correctamente.',
            'output' => $output,
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error al ejecutar el comando de impresión.',
            'output' => $output,
            'exitCode' => $exitCode,
        ]);
    }
    exit;
}

// Respuesta por defecto para rutas no manejadas
http_response_code(404);
echo json_encode(['error' => 'Ruta no encontrada']);
