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

function getJsonInput(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

function normalizeBool(mixed $value): bool
{
    return in_array(
        strtolower(trim((string)$value)),
        ['1', 'true', 't', 'si', 'yes', 'on'],
        true
    );
}

try {
    $globalStart = microtime(true);

    $timer = microtime(true);
    $pdo = getPdo();
    logTime('PDO connect/reuse', $timer);

    $method = $_SERVER['REQUEST_METHOD'];

    // =====================================================
    // GET OPTIMIZADO (UNA SOLA QUERY)
    // =====================================================
    if ($method === 'GET') {
        $timer = microtime(true);

        $stmt = $pdo->query("
            SELECT
                u.id AS usuario_id,
                u.nombre AS usuario_nombre,
                u.telefono AS usuario_telefono,
                u.email AS usuario_email,
                u.rol_id AS usuario_rol_id,
                LOWER(r.nombre) AS usuario_rol,
                COALESCE(
                    json_agg(
                        json_build_object(
                            'id', l.id,
                            'nombre_persona', l.nombre_persona,
                            'telefono', l.telefono,
                            'ingreso', l.ingreso,
                            'fecha_registro', l.fecha_registro
                        )
                        ORDER BY l.id
                    ) FILTER (WHERE l.id IS NOT NULL),
                    '[]'::json
                ) AS invitados
            FROM usuarios u
            INNER JOIN roles r ON r.id = u.rol_id
            LEFT JOIN listas l ON l.usuario_id = u.id
            WHERE
                u.activo = TRUE
                AND LOWER(r.nombre) IN ('administrador', 'promotor', 'promoter')
            GROUP BY u.id, r.nombre
            ORDER BY u.id
        ");

        $rows = $stmt->fetchAll() ?: [];
        logTime('GET listas query unica', $timer);

        $timer = microtime(true);
        foreach ($rows as &$row) {
            if (is_string($row['invitados'])) {
                $decoded = json_decode($row['invitados'], true);
                $row['invitados'] = is_array($decoded) ? $decoded : [];
            }
        }
        unset($row);
        logTime('GET listas armado PHP', $timer);

        logTime('TOTAL listas.php', $globalStart);
        jsonResponse(200, $rows);
    }

    // =====================================================
    // POST OPTIMIZADO
    // =====================================================
    if ($method === 'POST') {
        $input = getJsonInput();

        if (!isset($input['usuario_id']) || !isset($input['nombre_persona'])) {
            jsonResponse(400, ['error' => 'Campos obligatorios faltantes.']);
        }

        $usuarioId = (int)$input['usuario_id'];
        $nombrePersona = trim((string)$input['nombre_persona']);
        $telefono = $input['telefono'] ?? null;

        if ($usuarioId <= 0 || $nombrePersona === '') {
            jsonResponse(400, ['error' => 'Datos inválidos']);
        }

        $timer = microtime(true);
        $stmt = $pdo->prepare("
            SELECT
                u.activo,
                LOWER(r.nombre) AS rol_nombre
            FROM usuarios u
            INNER JOIN roles r ON r.id = u.rol_id
            WHERE u.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $usuarioId]);
        $usuario = $stmt->fetch();
        logTime('POST validar usuario', $timer);

        if ($usuario === false) {
            jsonResponse(404, ['error' => 'El usuario especificado no existe.']);
        }

        $activo = normalizeBool($usuario['activo'] ?? false);
        if (!$activo) {
            jsonResponse(403, ['error' => 'Usuario inactivo']);
        }

        $rol = strtolower(trim((string)($usuario['rol_nombre'] ?? '')));
        if (!in_array($rol, ['administrador', 'promotor', 'promoter'], true)) {
            jsonResponse(403, ['error' => 'Sin permisos']);
        }

        $timer = microtime(true);
        $stmt = $pdo->prepare("
            INSERT INTO listas (
                usuario_id,
                nombre_persona,
                telefono
            )
            VALUES (
                :usuario_id,
                :nombre_persona,
                :telefono
            )
            RETURNING id, nombre_persona, telefono
        ");
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':nombre_persona' => $nombrePersona,
            ':telefono' => $telefono,
        ]);
        $newGuest = $stmt->fetch();
        logTime('POST insert invitado', $timer);

        logTime('TOTAL listas.php', $globalStart);
        jsonResponse(200, $newGuest ?: []);
    }

    // =====================================================
    // PUT OPTIMIZADO
    // =====================================================
    if ($method === 'PUT') {
        if (!isset($_GET['id'])) {
            jsonResponse(400, ['error' => 'ID requerido']);
        }

        $id = (int)$_GET['id'];
        if ($id <= 0) {
            jsonResponse(400, ['error' => 'ID inválido']);
        }

        $input = getJsonInput();

        $timer = microtime(true);
        $stmt = $pdo->prepare("
            UPDATE listas
            SET
                nombre_persona = COALESCE(:nombre_persona, nombre_persona),
                telefono = COALESCE(:telefono, telefono)
            WHERE id = :id
            RETURNING id, nombre_persona, telefono
        ");
        $stmt->execute([
            ':id' => $id,
            ':nombre_persona' => $input['nombre_persona'] ?? null,
            ':telefono' => $input['telefono'] ?? null,
        ]);
        $updated = $stmt->fetch();
        logTime('PUT update invitado', $timer);

        logTime('TOTAL listas.php', $globalStart);
        jsonResponse(200, $updated ?: []);
    }

    // =====================================================
    // DELETE OPTIMIZADO
    // =====================================================
    if ($method === 'DELETE') {
        if (!isset($_GET['id'])) {
            jsonResponse(400, ['error' => 'ID requerido']);
        }

        $id = (int)$_GET['id'];
        if ($id <= 0) {
            jsonResponse(400, ['error' => 'ID inválido']);
        }

        $timer = microtime(true);
        $stmt = $pdo->prepare("DELETE FROM listas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        logTime('DELETE invitado', $timer);

        logTime('TOTAL listas.php', $globalStart);
        jsonResponse(200, ['success' => true]);
    }

    jsonResponse(405, ['error' => 'Método no permitido']);
} catch (Throwable $e) {
    error_log('listas.php ERROR: ' . $e->getMessage());

    jsonResponse(500, [
        'error' => 'Error interno',
        'detalle' => $e->getMessage(),
    ]);
}
