<?php

declare(strict_types=1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// RUTAS
if ($uriPath === '/api/dashboard.php' || $uriPath === '/api/dashboard') {
    require __DIR__ . '/api/dashboard.php';
    exit;
}

if ($uriPath === '/api/venta_entradas.php' || $uriPath === '/api/venta_entradas') {
    require __DIR__ . '/api/venta_entradas.php';
    exit;
}

// 404 limpio
header("Content-Type: application/json");
http_response_code(404);
echo json_encode([
    "error" => "Ruta no encontrada",
    "path" => $uriPath
]);
