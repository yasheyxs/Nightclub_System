<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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

function getJsonInput(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

try {
    $globalStart = microtime(true);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(405, ['ok' => false, 'error' => 'Método no permitido']);
    }

    $timer = microtime(true);
    $pdo = getPdo();
    logTime('PDO connect/reuse', $timer);

    $input = getJsonInput();

    $telefono = trim((string)($input['telefono'] ?? ''));
    $clave = (string)($input['password'] ?? '');

    if ($telefono === '' || $clave === '') {
        jsonResponse(400, [
            'ok' => false,
            'error' => 'Teléfono y contraseña obligatorios'
        ]);
    }

    // ============================
    // QUERY ÚNICA OPTIMIZADA
    // ============================
    $timer = microtime(true);

    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.telefono,
            u.nombre,
            u.email,
            u.rol_id,
            u.activo,
            u.clave_bcrypt,
            COALESCE(r.nombre,'') AS rol_nombre
        FROM usuarios u
        LEFT JOIN roles r ON r.id = u.rol_id
        WHERE u.telefono = :telefono
        LIMIT 1
    ");

    $stmt->execute([':telefono' => $telefono]);
    $usuario = $stmt->fetch();

    logTime('LOGIN query', $timer);

    if (!$usuario) {
        jsonResponse(401, ['ok' => false, 'error' => 'Credenciales inválidas']);
    }

    // ============================
    // VALIDACIÓN ACTIVO ULTRA RÁPIDA
    // ============================
    $activoRaw = $usuario['activo'];

    $estaActivo =
        $activoRaw === true ||
        $activoRaw === 1 ||
        $activoRaw === '1' ||
        $activoRaw === 't' ||
        $activoRaw === 'true';

    if (!$estaActivo) {
        jsonResponse(403, ['ok' => false, 'error' => 'Usuario inactivo']);
    }

    // ============================
    // PASSWORD VERIFY
    // ============================
    if (
        empty($usuario['clave_bcrypt']) ||
        !password_verify($clave, (string)$usuario['clave_bcrypt'])
    ) {
        jsonResponse(401, ['ok' => false, 'error' => 'Credenciales inválidas']);
    }

    // ============================
    // NORMALIZACIÓN ROL (SIN COSTO EXTRA)
    // ============================
    $rol = strtolower(trim((string)$usuario['rol_nombre']));

    if ($rol === 'administrador') $rol = 'admin';
    if ($rol === 'seller') $rol = 'vendedor';
    if ($rol === 'promoter') $rol = 'promotor';

    if ($rol === 'promotor') {
        jsonResponse(403, [
            'ok' => false,
            'error' => 'Promotores no pueden acceder'
        ]);
    }

    unset($usuario['clave_bcrypt']);

    $usuario['rol_slug'] = $rol !== '' ? $rol : null;
    $usuario['activo'] = true;

    // ============================
    // TOKEN ULTRA LIVIANO
    // ============================
    $token = bin2hex(random_bytes(32));

    logTime('TOTAL login.php', $globalStart);

    jsonResponse(200, [
        'ok' => true,
        'token' => $token,
        'user' => $usuario
    ]);
} catch (Throwable $e) {
    error_log('login.php ERROR: ' . $e->getMessage());

    jsonResponse(500, [
        'ok' => false,
        'error' => 'Error interno',
        'detalle' => $e->getMessage(),
    ]);
}
