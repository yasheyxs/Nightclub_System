<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
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

    $timer = microtime(true);
    $pdo = getPdo();
    logTime('PDO connect/reuse', $timer);

    $method = $_SERVER['REQUEST_METHOD'];

    // ============================
    // GET OPTIMIZADO
    // ============================
    if ($method === 'GET') {
        $eventoId = isset($_GET['evento_id']) ? (int) $_GET['evento_id'] : 0;
        $entradaId = isset($_GET['entrada_id']) ? (int) $_GET['entrada_id'] : 0;

        if ($eventoId <= 0 || $entradaId <= 0) {
            jsonResponse(400, ['error' => 'evento_id y entrada_id requeridos']);
        }

        $timer = microtime(true);

        $stmt = $pdo->prepare("
            SELECT
                u.id AS usuario_id,
                u.nombre AS usuario_nombre,
                LOWER(TRIM(r.nombre)) AS usuario_rol,
                pc.id,
                pc.cupo_total,
                pc.cupo_vendido
            FROM usuarios u
            INNER JOIN roles r ON r.id = u.rol_id
            LEFT JOIN promotores_cupos pc
                ON pc.usuario_id = u.id
               AND pc.evento_id = :evento_id
               AND pc.entrada_id = :entrada_id
            WHERE u.activo = true
              AND LOWER(TRIM(r.nombre)) IN ('promotor','promoter')
            ORDER BY u.nombre ASC
        ");

        $stmt->execute([
            ':evento_id' => $eventoId,
            ':entrada_id' => $entradaId,
        ]);

        $rows = $stmt->fetchAll() ?: [];
        logTime('GET promotores+cupo unica query', $timer);

        $response = [];

        foreach ($rows as $row) {
            $tieneCupo = $row['id'] !== null;

            $cupoTotal = $tieneCupo ? (int)$row['cupo_total'] : null;
            $cupoVendido = $tieneCupo ? (int)$row['cupo_vendido'] : null;

            $response[] = [
                'id' => $tieneCupo ? (int)$row['id'] : null,
                'usuario_id' => (int)$row['usuario_id'],
                'usuario_nombre' => $row['usuario_nombre'],
                'usuario_rol' => $row['usuario_rol'],
                'es_promotor' => true,
                'evento_id' => $eventoId,
                'entrada_id' => $entradaId,
                'cupo_total' => $cupoTotal,
                'cupo_vendido' => $cupoVendido,
                'cupo_disponible' => $tieneCupo ? max(0, $cupoTotal - $cupoVendido) : null,
                'tiene_cupo' => $tieneCupo,
            ];
        }

        logTime('TOTAL promotores.php', $globalStart);
        jsonResponse(200, $response);
    }

    // ============================
    // POST OPTIMIZADO
    // ============================
    if ($method === 'POST') {
        $input = getJsonInput();

        $usuarioId = (int)($input['usuario_id'] ?? 0);
        $eventoId = (int)($input['evento_id'] ?? 0);
        $entradaId = (int)($input['entrada_id'] ?? 0);
        $cupoTotal = isset($input['cupo_total']) ? (int)$input['cupo_total'] : null;

        if ($usuarioId <= 0 || $eventoId <= 0 || $entradaId <= 0 || $cupoTotal === null) {
            jsonResponse(400, ['error' => 'datos incompletos']);
        }

        $timer = microtime(true);

        $stmt = $pdo->prepare("
            INSERT INTO promotores_cupos (
                usuario_id,
                evento_id,
                entrada_id,
                cupo_total
            )
            VALUES (
                :usuario_id,
                :evento_id,
                :entrada_id,
                :cupo_total
            )
            ON CONFLICT (usuario_id, evento_id, entrada_id)
            DO UPDATE SET cupo_total = EXCLUDED.cupo_total
            RETURNING id, usuario_id, evento_id, entrada_id, cupo_total, cupo_vendido
        ");

        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':evento_id' => $eventoId,
            ':entrada_id' => $entradaId,
            ':cupo_total' => $cupoTotal,
        ]);

        $row = $stmt->fetch();
        logTime('POST upsert cupo', $timer);

        logTime('TOTAL promotores.php', $globalStart);

        jsonResponse(200, [
            'id' => (int)$row['id'],
            'usuario_id' => (int)$row['usuario_id'],
            'evento_id' => (int)$row['evento_id'],
            'entrada_id' => (int)$row['entrada_id'],
            'cupo_total' => (int)$row['cupo_total'],
            'cupo_vendido' => (int)$row['cupo_vendido'],
            'cupo_disponible' => max(0, (int)$row['cupo_total'] - (int)$row['cupo_vendido']),
            'tiene_cupo' => true,
        ]);
    }

    jsonResponse(405, ['error' => 'Método no permitido']);
} catch (Throwable $e) {
    error_log('promotores.php ERROR: ' . $e->getMessage());

    jsonResponse(500, [
        'error' => 'Error interno',
        'detalle' => $e->getMessage(),
    ]);
}
