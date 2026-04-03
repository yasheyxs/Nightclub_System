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

    $eventoId = (int)($input['evento_id'] ?? 0);
    $entradaId = (int)($input['entrada_id'] ?? 0);
    $cantidad = (int)($input['cantidad'] ?? 0);
    $usuarioId = isset($input['usuario_id']) ? (int)$input['usuario_id'] : null;
    $motivo = trim((string)($input['motivo'] ?? 'Ajuste manual'));

    if ($eventoId <= 0 || $entradaId <= 0 || $cantidad <= 0) {
        jsonResponse(400, ['ok' => false, 'error' => 'Datos inválidos']);
    }

    // ============================
    // VALIDACIÓN ULTRA OPTIMIZADA
    // ============================
    $timer = microtime(true);

    $stmt = $pdo->prepare("
        SELECT
            (SELECT nombre FROM eventos WHERE id = :evento_id LIMIT 1) AS evento_nombre,
            (SELECT nombre FROM entradas WHERE id = :entrada_id LIMIT 1) AS entrada_nombre,
            (
                SELECT COUNT(*)
                FROM ventas_entradas
                WHERE evento_id = :evento_id
                  AND entrada_id = :entrada_id
                  AND estado = 'comprada'
            ) AS disponibles
    ");

    $stmt->execute([
        ':evento_id' => $eventoId,
        ':entrada_id' => $entradaId
    ]);

    $meta = $stmt->fetch();
    logTime('validacion unica query', $timer);

    if (!$meta['evento_nombre']) {
        jsonResponse(404, ['ok' => false, 'error' => 'Evento no encontrado']);
    }

    if (!$meta['entrada_nombre']) {
        jsonResponse(404, ['ok' => false, 'error' => 'Entrada no encontrada']);
    }

    $disponibles = (int)$meta['disponibles'];

    if ($disponibles <= 0) {
        jsonResponse(400, ['ok' => false, 'error' => 'No hay entradas disponibles']);
    }

    if ($cantidad > $disponibles) {
        jsonResponse(400, [
            'ok' => false,
            'error' => 'Cantidad excede disponibles',
            'disponibles' => $disponibles
        ]);
    }

    // ============================
    // TRANSACCIÓN OPTIMIZADA
    // ============================
    $pdo->beginTransaction();

    $timer = microtime(true);

    $stmt = $pdo->prepare("
        WITH cte AS (
            SELECT id, promotor_id
            FROM ventas_entradas
            WHERE evento_id = :evento_id
              AND entrada_id = :entrada_id
              AND estado = 'comprada'
            ORDER BY id DESC
            LIMIT :cantidad
            FOR UPDATE
        )
        UPDATE ventas_entradas v
        SET
            estado = 'anulada',
            observaciones = CASE
                WHEN COALESCE(v.observaciones,'') = ''
                THEN :obs
                ELSE v.observaciones || ' | ' || :obs
            END
        FROM cte
        WHERE v.id = cte.id
        RETURNING v.id, v.promotor_id
    ");

    $obs = $usuarioId
        ? $motivo . ' | user=' . $usuarioId
        : $motivo;

    $stmt->bindValue(':evento_id', $eventoId, PDO::PARAM_INT);
    $stmt->bindValue(':entrada_id', $entradaId, PDO::PARAM_INT);
    $stmt->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
    $stmt->bindValue(':obs', $obs, PDO::PARAM_STR);
    $stmt->execute();

    $rows = $stmt->fetchAll();
    logTime('update anulacion', $timer);

    if (count($rows) !== $cantidad) {
        $pdo->rollBack();
        jsonResponse(409, ['ok' => false, 'error' => 'Conflicto al anular']);
    }

    // ============================
    // UPDATE CUPOS AGRUPADO
    // ============================
    $timer = microtime(true);

    $map = [];
    foreach ($rows as $r) {
        $pid = (int)$r['promotor_id'];
        if ($pid > 0) {
            $map[$pid] = ($map[$pid] ?? 0) + 1;
        }
    }

    if ($map) {
        $stmt = $pdo->prepare("
            UPDATE promotores_cupos
            SET cupo_vendido = GREATEST(cupo_vendido - :cant, 0)
            WHERE usuario_id = :uid
              AND evento_id = :evento_id
              AND entrada_id = :entrada_id
        ");

        foreach ($map as $uid => $cant) {
            $stmt->execute([
                ':cant' => $cant,
                ':uid' => $uid,
                ':evento_id' => $eventoId,
                ':entrada_id' => $entradaId
            ]);
        }
    }

    logTime('update cupos', $timer);

    $pdo->commit();

    logTime('TOTAL anular.php', $globalStart);

    jsonResponse(200, [
        'ok' => true,
        'cantidad' => $cantidad,
        'evento_nombre' => $meta['evento_nombre'],
        'entrada_nombre' => $meta['entrada_nombre']
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('anular.php ERROR: ' . $e->getMessage());

    jsonResponse(500, [
        'ok' => false,
        'error' => 'Error interno',
        'detalle' => $e->getMessage()
    ]);
}
