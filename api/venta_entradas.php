<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
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
        ['1', 'true', 'si', 'yes', 'on'],
        true
    );
}

function generarQrCodigo(): string
{
    return 'SANTAS-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function generarQrHash(string $qr): string
{
    return hash('sha256', $qr);
}

function obtenerTextoTrago(bool $incluyeTrago): string
{
    return $incluyeTrago ? 'INCLUYE TRAGO GRATIS' : '';
}

function obtenerEventoParaVenta(PDO $pdo, int $eventoId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            e.id,
            e.nombre,
            e.capacidad,
            e.activo
        FROM eventos e
        WHERE e.id = :id
          AND e.activo = TRUE
          AND NOT EXISTS (
              SELECT 1
              FROM cierres_eventos ce
              WHERE ce.evento_id = e.id
          )
        LIMIT 1
    ");
    $stmt->execute([':id' => $eventoId]);

    $evento = $stmt->fetch();

    return $evento !== false ? $evento : null;
}

try {
    $globalStart = microtime(true);

    $timer = microtime(true);
    $pdo = getPdo();
    logTime('PDO connect/reuse', $timer);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $timer = microtime(true);
        $eventos = $pdo->query("
            SELECT
                e.*
            FROM eventos e
            WHERE e.activo = TRUE
              AND NOT EXISTS (
                  SELECT 1
                  FROM cierres_eventos ce
                  WHERE ce.evento_id = e.id
              )
            ORDER BY e.fecha ASC
        ")->fetchAll();
        logTime('GET eventos', $timer);

        $timer = microtime(true);
        $entradas = $pdo->query("
            SELECT *
            FROM entradas
        ")->fetchAll();
        logTime('GET entradas', $timer);

        $timer = microtime(true);
        $ventas = $pdo->query("
            SELECT
                evento_id,
                entrada_id,
                SUM(cantidad) AS total_vendido
            FROM ventas_entradas
            WHERE estado = 'comprada'
            GROUP BY evento_id, entrada_id
        ")->fetchAll();
        logTime('GET ventas agrupadas', $timer);

        logTime('TOTAL venta_entradas.php', $globalStart);

        jsonResponse(200, [
            'eventos' => $eventos,
            'entradas' => $entradas,
            'ventas' => $ventas,
        ]);
    }

    $input = getJsonInput();
    $accion = strtolower(trim((string)($input['accion'] ?? 'sumar')));

    if ($accion === 'cerrar_evento') {
        $eventoId = (int)($input['evento_id'] ?? 0);

        if ($eventoId <= 0) {
            jsonResponse(400, [
                'ok' => false,
                'error' => 'Evento inválido',
            ]);
        }

        $timer = microtime(true);
        $evento = obtenerEventoParaVenta($pdo, $eventoId);
        logTime('cerrar_evento obtenerEventoParaVenta', $timer);

        if ($evento === null) {
            jsonResponse(404, [
                'ok' => false,
                'error' => 'El evento no existe, está inactivo o ya fue cerrado.',
            ]);
        }

        $pdo->beginTransaction();

        try {
            $timer = microtime(true);
            $totalesStmt = $pdo->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN estado IN ('comprada', 'usada') THEN cantidad ELSE 0 END), 0) AS total_vendido,
                    COALESCE(SUM(CASE WHEN estado IN ('comprada', 'usada') THEN COALESCE(total, precio_unitario * cantidad) ELSE 0 END), 0) AS total_monto
                FROM ventas_entradas
                WHERE evento_id = :evento_id
            ");
            $totalesStmt->execute([':evento_id' => $eventoId]);
            $totales = $totalesStmt->fetch() ?: [
                'total_vendido' => 0,
                'total_monto' => 0,
            ];
            logTime('cerrar_evento totales', $timer);

            $timer = microtime(true);
            $detalleStmt = $pdo->prepare("
                SELECT
                    ve.entrada_id,
                    COALESCE(en.nombre, CONCAT('Entrada ', ve.entrada_id::text)) AS entrada_nombre,
                    COALESCE(SUM(ve.cantidad), 0) AS cantidad,
                    COALESCE(SUM(COALESCE(ve.total, ve.precio_unitario * ve.cantidad)), 0) AS monto
                FROM ventas_entradas ve
                LEFT JOIN entradas en
                    ON en.id = ve.entrada_id
                WHERE ve.evento_id = :evento_id
                  AND ve.estado IN ('comprada', 'usada')
                GROUP BY ve.entrada_id, entrada_nombre
                ORDER BY entrada_nombre ASC
            ");
            $detalleStmt->execute([':evento_id' => $eventoId]);
            $detalle = $detalleStmt->fetchAll() ?: [];
            logTime('cerrar_evento detalle', $timer);

            $capacidad = (int)($evento['capacidad'] ?? 0);
            $totalVendido = (int)($totales['total_vendido'] ?? 0);
            $porcentaje = $capacidad > 0
                ? round(($totalVendido * 100) / $capacidad, 2)
                : null;

            $timer = microtime(true);
            $cierreStmt = $pdo->prepare("
                INSERT INTO cierres_eventos (
                    evento_id,
                    evento_nombre,
                    total_vendido,
                    total_monto,
                    capacidad,
                    porcentaje,
                    detalle,
                    fecha_cierre
                )
                VALUES (
                    :evento_id,
                    :evento_nombre,
                    :total_vendido,
                    :total_monto,
                    :capacidad,
                    :porcentaje,
                    :detalle::jsonb,
                    NOW()
                )
            ");
            $cierreStmt->execute([
                ':evento_id' => $eventoId,
                ':evento_nombre' => (string)$evento['nombre'],
                ':total_vendido' => $totalVendido,
                ':total_monto' => (float)($totales['total_monto'] ?? 0),
                ':capacidad' => $capacidad,
                ':porcentaje' => $porcentaje,
                ':detalle' => json_encode($detalle, JSON_UNESCAPED_UNICODE),
            ]);
            logTime('cerrar_evento insert cierre', $timer);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        logTime('TOTAL venta_entradas.php', $globalStart);

        jsonResponse(200, [
            'ok' => true,
            'mensaje' => 'Evento cerrado correctamente',
        ]);
    }

    $entradaId = (int)($input['entrada_id'] ?? 0);
    $cantidad = (int)($input['cantidad'] ?? 1);
    $eventoId = (int)($input['evento_id'] ?? 0);
    $trago = normalizeBool($input['incluye_trago'] ?? false);
    $usuarioId = (int)($input['usuario_id'] ?? 0);

    if ($entradaId <= 0 || $eventoId <= 0 || $cantidad <= 0) {
        jsonResponse(400, [
            'ok' => false,
            'error' => 'Datos inválidos',
        ]);
    }

    $timer = microtime(true);
    $evento = obtenerEventoParaVenta($pdo, $eventoId);
    logTime('venta obtenerEventoParaVenta', $timer);

    if ($evento === null) {
        jsonResponse(409, [
            'ok' => false,
            'error' => 'No se puede vender: el evento no existe, está inactivo o ya fue cerrado.',
        ]);
    }

    $timer = microtime(true);
    $stmtEntrada = $pdo->prepare("
        SELECT
            id,
            nombre,
            precio_base
        FROM entradas
        WHERE id = :id
        LIMIT 1
    ");
    $stmtEntrada->execute([':id' => $entradaId]);
    $entrada = $stmtEntrada->fetch();
    logTime('venta obtener entrada', $timer);

    if ($entrada === false) {
        jsonResponse(404, [
            'ok' => false,
            'error' => 'Entrada no encontrada',
        ]);
    }

    $precioBase = (float)$entrada['precio_base'];

    $tickets = [];
    $printJobs = [];
    $warnings = [];

    $pdo->beginTransaction();

    try {
        $timer = microtime(true);
        $insertStmt = $pdo->prepare("
            INSERT INTO ventas_entradas (
                entrada_id,
                cantidad,
                precio_unitario,
                evento_id,
                estado,
                incluye_trago,
                qr_codigo,
                qr_hash
            )
            VALUES (
                :entrada_id,
                1,
                :precio_unitario,
                :evento_id,
                'comprada',
                :incluye_trago,
                :qr_codigo,
                :qr_hash
            )
            RETURNING *
        ");
        logTime('venta prepare insert', $timer);

        $timerVentas = microtime(true);

        for ($i = 0; $i < $cantidad; $i++) {
            $qr = generarQrCodigo();
            $hash = generarQrHash($qr);

            $insertStmt->bindValue(':entrada_id', $entradaId, PDO::PARAM_INT);
            $insertStmt->bindValue(':precio_unitario', $precioBase);
            $insertStmt->bindValue(':evento_id', $eventoId, PDO::PARAM_INT);
            $insertStmt->bindValue(':incluye_trago', $trago, PDO::PARAM_BOOL);
            $insertStmt->bindValue(':qr_codigo', $qr, PDO::PARAM_STR);
            $insertStmt->bindValue(':qr_hash', $hash, PDO::PARAM_STR);
            $insertStmt->execute();

            $ticket = $insertStmt->fetch();

            if ($ticket === false) {
                throw new RuntimeException('No se pudo registrar la venta.');
            }

            $tickets[] = $ticket;

            $printJobs[] = [
                'ticket_id' => (int)($ticket['id'] ?? 0),
                'evento_id' => $eventoId,
                'entrada_id' => $entradaId,
                'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
                'tipo' => (string)$entrada['nombre'],
                'precio' => (int)round($precioBase),
                'precio_formateado' => number_format($precioBase, 0, ',', '.'),
                'incluye_trago' => $trago,
                'trago_texto' => obtenerTextoTrago($trago),
                'qr' => $qr,
                'estado' => 'comprada',
                'fecha' => date('d/m/Y'),
                'hora' => date('H:i'),
                'negocio' => 'SANTAS',
                'ancho_papel' => '80mm',
            ];
        }

        logTime('venta inserts loop', $timerVentas);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    logTime('TOTAL venta_entradas.php', $globalStart);

    jsonResponse(200, [
        'ok' => true,
        'tickets' => $tickets,
        'print_jobs' => $printJobs,
        'warnings' => $warnings,
    ]);
} catch (Throwable $e) {
    error_log('venta_entradas.php ERROR: ' . $e->getMessage());

    jsonResponse(500, [
        'ok' => false,
        'error' => $e->getMessage(),
        'recovery' => 'Podés reintentar la operación',
    ]);
}
