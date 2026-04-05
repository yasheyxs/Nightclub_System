<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

date_default_timezone_set('America/Argentina/Cordoba');

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

function getClientIp(): ?string
{
    return $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['HTTP_CLIENT_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? null;
}

function contarEntradasEscaneadas(PDO $pdo, ?int $eventoId): int
{
    if ($eventoId === null || $eventoId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)::int
        FROM accesos_qr aq
        INNER JOIN ventas_entradas v
            ON v.id = aq.venta_entrada_id
        WHERE v.evento_id = :evento_id
          AND aq.resultado = 'valido'
    ");
    $stmt->execute([':evento_id' => $eventoId]);

    return (int)$stmt->fetchColumn();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(405, ['ok' => false, 'error' => 'Método no permitido']);
    }

    $pdo = getPdo();
    $input = getJsonInput();

    $qrCodigo = trim((string)($input['qr_codigo'] ?? ''));
    $usuarioValidadorId = isset($input['usuario_validador_id']) ? (int)$input['usuario_validador_id'] : null;
    $dispositivo = isset($input['dispositivo']) ? substr((string)($input['dispositivo'] ?? ''), 0, 100) : null;
    $observaciones = isset($input['observaciones']) ? trim((string)$input['observaciones']) : null;
    $ip = substr((string)(getClientIp() ?? ''), 0, 100);

    if ($qrCodigo === '') {
        jsonResponse(400, [
            'ok' => false,
            'resultado' => 'invalido',
            'mensaje' => 'QR requerido',
            'entradas_escaneadas' => 0,
        ]);
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT
            v.id,
            v.estado,
            v.nombre,
            v.dni,
            v.incluye_trago,
            v.fecha_venta,
            v.qr_generado_at,
            v.qr_usado_at,
            e.nombre AS entrada_nombre,
            ev.id AS evento_id,
            ev.nombre AS evento_nombre,
            ev.activo
        FROM ventas_entradas v
        LEFT JOIN entradas e
            ON e.id = v.entrada_id
        LEFT JOIN eventos ev
            ON ev.id = v.evento_id
        WHERE v.qr_codigo = :qr
        LIMIT 1
        FOR UPDATE OF v
    ");
    $stmt->execute([':qr' => $qrCodigo]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($venta === false) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        jsonResponse(404, [
            'ok' => false,
            'resultado' => 'invalido',
            'mensaje' => 'QR inválido',
            'entradas_escaneadas' => 0,
        ]);
    }

    $ventaId = (int)$venta['id'];
    $eventoId = isset($venta['evento_id']) && $venta['evento_id'] !== null
        ? (int)$venta['evento_id']
        : null;
    $estado = strtolower((string)($venta['estado'] ?? ''));

    $resultado = 'valido';
    $mensaje = 'Ingreso válido';

    if ($estado === 'anulada') {
        $resultado = 'anulado';
        $mensaje = 'Entrada anulada';
    } elseif ($estado === 'usada') {
        $resultado = 'usado';
        $mensaje = 'Entrada ya usada';
    } elseif (!in_array($estado, ['comprada', 'impresa'], true)) {
        $resultado = 'invalido';
        $mensaje = 'Estado inválido';
    }

    $stmt = $pdo->prepare("
        INSERT INTO accesos_qr (
            venta_entrada_id,
            qr_codigo,
            fecha_escaneo,
            usuario_validador_id,
            resultado,
            observaciones,
            dispositivo,
            ip
        ) VALUES (
            :vid,
            :qr,
            NOW(),
            :uid,
            :res,
            :obs,
            :disp,
            :ip
        )
    ");
    $stmt->execute([
        ':vid' => $ventaId,
        ':qr' => $qrCodigo,
        ':uid' => $usuarioValidadorId,
        ':res' => $resultado,
        ':obs' => $observaciones,
        ':disp' => $dispositivo,
        ':ip' => $ip,
    ]);

    if ($resultado === 'valido') {
        $stmt = $pdo->prepare("
            UPDATE ventas_entradas
            SET
                estado = 'usada',
                qr_usado_at = COALESCE(qr_usado_at, NOW())
            WHERE id = :id
              AND estado IN ('comprada', 'impresa')
            RETURNING estado, qr_usado_at
        ");
        $stmt->execute([':id' => $ventaId]);
        $updated = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($updated === false) {
            $resultado = 'usado';
            $mensaje = 'Entrada ya usada';
        } else {
            $venta['estado'] = $updated['estado'];
            $venta['qr_usado_at'] = $updated['qr_usado_at'];
        }
    }

    $entradasEscaneadas = contarEntradasEscaneadas($pdo, $eventoId);

    $pdo->commit();

    jsonResponse(200, [
        'ok' => $resultado === 'valido',
        'resultado' => $resultado,
        'mensaje' => $mensaje,
        'entradas_escaneadas' => $entradasEscaneadas,
        'data' => [
            'venta_id' => $ventaId,
            'evento_id' => $eventoId,
            'entrada' => $venta['entrada_nombre'] ?? null,
            'evento' => $venta['evento_nombre'] ?? null,
            'nombre' => $venta['nombre'] ?? null,
            'dni' => $venta['dni'] ?? null,
            'incluye_trago' => filter_var($venta['incluye_trago'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'estado' => $venta['estado'] ?? null,
            'fecha_venta' => $venta['fecha_venta'] ?? null,
            'qr_generado_at' => $venta['qr_generado_at'] ?? null,
            'qr_usado_at' => $venta['qr_usado_at'] ?? null,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('validar_qr ERROR: ' . $e->getMessage());

    jsonResponse(500, [
        'ok' => false,
        'resultado' => 'invalido',
        'mensaje' => 'Error interno',
        'error' => 'Error interno',
        'detalle' => $e->getMessage(),
        'entradas_escaneadas' => 0,
    ]);
}
