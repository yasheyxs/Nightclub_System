<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

<?php
require_once __DIR__ . '/api/cors.php';
header('Content-Type: application/json; charset=utf-8');

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = trim($uri, '/');

// map routes to api php files
$map = [
    'dashboard' => 'dashboard.php',
    'eventos' => 'eventos.php',
    'entradas' => 'entradas.php',
    'anticipadas' => 'anticipadas.php',
    'venta_entradas' => 'venta_entradas.php',
    'usuarios' => 'usuarios.php',
];

if (isset($map[$uri])) {
    $file = __DIR__ . '/api/' . $map[$uri];
    if (file_exists($file)) {
        require $file;
        exit;
    }
}

http_response_code(404);
echo json_encode(['error' => 'Endpoint no encontrado']);