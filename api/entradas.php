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

// =========================
// HELPERS
// =========================
function logTime(string $label, float $start): void
{
    error_log($label . ': ' . round((microtime(true) - $start) * 1000, 2) . ' ms');
}

function jsonResponse(int $statusCode, array $data): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getJsonInput(): array
{
    $raw = file_get_contents("php://input") ?: '';
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function toBoolOrNull(mixed $value): ?bool
{
    if ($value === null) return null;

    if ($value === true || $value === 1 || $value === "1" || $value === "true") return true;
    if ($value === false || $value === 0 || $value === "0" || $value === "false") return false;

    return null;
}

function isValidTimeOrNull(mixed $value): bool
{
    if ($value === null || $value === '') return true;
    return preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string)$value) === 1;
}

function sanitize(array $input, bool $isUpdate): array
{
    $nombre = $input['nombre'] ?? null;
    $precioBase = $input['precio_base'] ?? null;

    if (!$isUpdate) {
        if (!$nombre) jsonResponse(400, ["ok" => false, "message" => "nombre requerido"]);
        if ($precioBase === null) jsonResponse(400, ["ok" => false, "message" => "precio_base requerido"]);
    }

    if ($precioBase !== null && !is_numeric($precioBase)) {
        jsonResponse(400, ["ok" => false, "message" => "precio_base inválido"]);
    }

    if (isset($input['nuevo_precio']) && $input['nuevo_precio'] !== null && !is_numeric($input['nuevo_precio'])) {
        jsonResponse(400, ["ok" => false, "message" => "nuevo_precio inválido"]);
    }

    if (!isValidTimeOrNull($input['hora_inicio_cambio'] ?? null)) {
        jsonResponse(400, ["ok" => false, "message" => "hora_inicio inválida"]);
    }

    if (!isValidTimeOrNull($input['hora_fin_cambio'] ?? null)) {
        jsonResponse(400, ["ok" => false, "message" => "hora_fin inválida"]);
    }

    return [
        "nombre" => $nombre,
        "descripcion" => $input['descripcion'] ?? null,
        "precio_base" => $precioBase,
        "cambio_automatico" => toBoolOrNull($input['cambio_automatico'] ?? null),
        "hora_inicio_cambio" => $input['hora_inicio_cambio'] ?? null,
        "hora_fin_cambio" => $input['hora_fin_cambio'] ?? null,
        "nuevo_precio" => $input['nuevo_precio'] ?? null,
        "activo" => toBoolOrNull($input['activo'] ?? null),
    ];
}

// =========================
// MAIN
// =========================
try {
    $globalStart = microtime(true);

    $timer = microtime(true);
    $pdo = getPdo();
    logTime('PDO connect/reuse', $timer);

    $method = $_SERVER['REQUEST_METHOD'];
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    // =========================
    // GET OPTIMIZADO
    // =========================
    if ($method === 'GET') {
        $timer = microtime(true);

        if ($id) {
            $stmt = $pdo->prepare("
                SELECT * FROM entradas
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();

            if (!$row) jsonResponse(404, ["ok" => false, "message" => "No encontrada"]);

            logTime('GET detalle entrada', $timer);
            logTime('TOTAL entradas.php', $globalStart);

            jsonResponse(200, ["ok" => true, "data" => $row]);
        }

        $rows = $pdo->query("SELECT * FROM entradas ORDER BY id ASC")->fetchAll();

        logTime('GET listado entradas', $timer);
        logTime('TOTAL entradas.php', $globalStart);

        jsonResponse(200, ["ok" => true, "data" => $rows ?: []]);
    }

    // =========================
    // POST OPTIMIZADO
    // =========================
    if ($method === 'POST') {
        $input = getJsonInput();
        $data = sanitize($input, false);

        $timer = microtime(true);

        $stmt = $pdo->prepare("
            INSERT INTO entradas (
                nombre, descripcion, precio_base,
                cambio_automatico, hora_inicio_cambio,
                hora_fin_cambio, nuevo_precio, activo
            )
            VALUES (
                :nombre, :descripcion, :precio_base,
                :cambio_automatico, :hora_inicio_cambio,
                :hora_fin_cambio, :nuevo_precio, :activo
            )
            RETURNING *
        ");

        $stmt->execute([
            ':nombre' => $data['nombre'],
            ':descripcion' => $data['descripcion'],
            ':precio_base' => $data['precio_base'],
            ':cambio_automatico' => $data['cambio_automatico'] ?? false,
            ':hora_inicio_cambio' => $data['hora_inicio_cambio'],
            ':hora_fin_cambio' => $data['hora_fin_cambio'],
            ':nuevo_precio' => $data['nuevo_precio'],
            ':activo' => $data['activo'] ?? true,
        ]);

        $row = $stmt->fetch();

        logTime('POST entrada', $timer);
        logTime('TOTAL entradas.php', $globalStart);

        jsonResponse(201, ["ok" => true, "data" => $row]);
    }

    // =========================
    // PUT OPTIMIZADO
    // =========================
    if ($method === 'PUT') {
        if (!$id) jsonResponse(400, ["ok" => false, "message" => "ID requerido"]);

        $input = getJsonInput();
        $data = sanitize($input, true);

        $timer = microtime(true);

        $stmt = $pdo->prepare("
            UPDATE entradas SET
                nombre = COALESCE(:nombre, nombre),
                descripcion = COALESCE(:descripcion, descripcion),
                precio_base = COALESCE(:precio_base, precio_base),
                cambio_automatico = COALESCE(:cambio_automatico, cambio_automatico),
                hora_inicio_cambio = COALESCE(:hora_inicio_cambio, hora_inicio_cambio),
                hora_fin_cambio = COALESCE(:hora_fin_cambio, hora_fin_cambio),
                nuevo_precio = COALESCE(:nuevo_precio, nuevo_precio),
                activo = COALESCE(:activo, activo)
            WHERE id = :id
            RETURNING *
        ");

        $stmt->execute([
            ':id' => $id,
            ':nombre' => $data['nombre'],
            ':descripcion' => $data['descripcion'],
            ':precio_base' => $data['precio_base'],
            ':cambio_automatico' => $data['cambio_automatico'],
            ':hora_inicio_cambio' => $data['hora_inicio_cambio'],
            ':hora_fin_cambio' => $data['hora_fin_cambio'],
            ':nuevo_precio' => $data['nuevo_precio'],
            ':activo' => $data['activo'],
        ]);

        $row = $stmt->fetch();

        if (!$row) jsonResponse(404, ["ok" => false, "message" => "No encontrada"]);

        logTime('PUT entrada', $timer);
        logTime('TOTAL entradas.php', $globalStart);

        jsonResponse(200, ["ok" => true, "data" => $row]);
    }

    // =========================
    // DELETE OPTIMIZADO
    // =========================
    if ($method === 'DELETE') {
        if (!$id) jsonResponse(400, ["ok" => false, "message" => "ID requerido"]);

        $timer = microtime(true);

        $stmt = $pdo->prepare("
            DELETE FROM entradas
            WHERE id = :id
            RETURNING id
        ");
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();

        if (!$row) jsonResponse(404, ["ok" => false, "message" => "No encontrada"]);

        logTime('DELETE entrada', $timer);
        logTime('TOTAL entradas.php', $globalStart);

        jsonResponse(200, ["ok" => true]);
    }

    jsonResponse(405, ["ok" => false, "message" => "Método no permitido"]);
} catch (Throwable $e) {
    error_log('entradas.php ERROR: ' . $e->getMessage());

    jsonResponse(500, [
        "ok" => false,
        "error" => "Error interno",
        "detalle" => $e->getMessage()
    ]);
}
