<?php

declare(strict_types=1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

switch ($uriPath) {
    case '/api/dashboard':
    case '/api/dashboard.php':
        require __DIR__ . '/api/dashboard.php';
        exit;

    case '/api/login':
    case '/api/login.php':
        require __DIR__ . '/api/login.php';
        exit;

    case '/api/venta_entradas':
    case '/api/venta_entradas.php':
        require __DIR__ . '/api/venta_entradas.php';
        exit;

    case '/api/anular_entrada':
    case '/api/anular_entrada.php':
        require __DIR__ . '/api/anular_entrada.php';
        exit;

    case '/api/validar_qr':
    case '/api/validar_qr.php':
        require __DIR__ . '/api/validar_qr.php';
        exit;

    case '/api/anticipadas':
    case '/api/anticipadas.php':
        require __DIR__ . '/api/anticipadas.php';
        exit;

    case '/api/entradas':
    case '/api/entradas.php':
        require __DIR__ . '/api/entradas.php';
        exit;

    case '/api/eventos':
    case '/api/eventos.php':
        require __DIR__ . '/api/eventos.php';
        exit;

    case '/api/promotores':
    case '/api/promotores.php':
        require __DIR__ . '/api/promotores.php';
        exit;

    case '/api/listas':
    case '/api/listas.php':
        require __DIR__ . '/api/listas.php';
        exit;

    case '/api/roles':
    case '/api/roles.php':
        require __DIR__ . '/api/roles.php';
        exit;

    case '/api/usuarios':
    case '/api/usuarios.php':
        require __DIR__ . '/api/usuarios.php';
        exit;
}

header('Content-Type: application/json; charset=utf-8');
http_response_code(404);
echo json_encode([
    'ok' => false,
    'error' => 'Ruta no encontrada',
    'path' => $uriPath
], JSON_UNESCAPED_UNICODE);
