<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=utf-8");

ini_set('display_errors', '0');
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

date_default_timezone_set('America/Argentina/Cordoba');

function logTime(string $label, float $start): void
{
    error_log($label . ': ' . round((microtime(true) - $start) * 1000, 2) . ' ms');
}

function jsonResponse(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $globalStart = microtime(true);

    $timer = microtime(true);
    $pdo = getPdo();
    logTime('PDO connect/reuse', $timer);

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(405, ['error' => 'Método no permitido']);
    }

    $timer = microtime(true);
    $stmt = $pdo->query("
        SELECT
            id,
            nombre
        FROM roles
        ORDER BY id ASC
    ");
    $roles = $stmt->fetchAll() ?: [];
    logTime('GET roles query', $timer);

    logTime('TOTAL roles.php', $globalStart);
    jsonResponse(200, $roles);
} catch (Throwable $e) {
    error_log('roles.php ERROR: ' . $e->getMessage());

    jsonResponse(500, [
        'error' => 'Error interno',
        'detalle' => $e->getMessage(),
    ]);
}
