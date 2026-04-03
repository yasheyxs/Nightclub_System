<?php

declare(strict_types=1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

date_default_timezone_set('America/Argentina/Cordoba');

// ======================================
// CONFIG DB
// ======================================
$host = "aws-1-us-east-2.pooler.supabase.com";
$port = "5432";
$dbname = "postgres";
$user = "postgres.kxvogvgsgwfvtmidabyp";
$password = "lapicero30!";

// ======================================
// HELPERS
// ======================================
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

function normalizeBool($v): bool
{
    return in_array(strtolower((string)$v), ['1', 'true', 'si', 'yes', 'on'], true);
}

function generarQrCodigo(): string
{
    return 'SANTAS-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function generarQrHash(string $qr): string
{
    return hash('sha256', $qr);
}

function db(): PDO
{
    global $host, $port, $dbname, $user, $password;

    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password;sslmode=require"
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function obtenerTextoTrago(bool $incluyeTrago): string
{
    return $incluyeTrago ? 'INCLUYE TRAGO GRATIS' : '';
}

function eventoEstaCerrado(PDO $pdo, int $eventoId): bool
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM cierres_eventos
        WHERE evento_id = :evento_id
        LIMIT 1
    ");
    $stmt->execute([':evento_id' => $eventoId]);
    return (bool)$stmt->fetchColumn();
}

function obtenerEventoParaVenta(PDO $pdo, int $eventoId): ?array
{
    $stmt = $pdo->prepare("
        SELECT id, nombre, capacidad, activo
        FROM eventos
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $eventoId]);
    $evento = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$evento) {
        return null;
    }

    if (!filter_var($evento['activo'], FILTER_VALIDATE_BOOLEAN)) {
        return null;
    }

    if (eventoEstaCerrado($pdo, (int)$evento['id'])) {
        return null;
    }

    return $evento;
}

// ======================================
// MAIN
// ======================================
try {
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        jsonResponse(200, [
            'eventos' => $pdo->query("
                SELECT e.*
                FROM eventos e
                WHERE e.activo = true
                  AND NOT EXISTS (
                      SELECT 1
                      FROM cierres_eventos ce
                      WHERE ce.evento_id = e.id
                  )
                ORDER BY e.fecha ASC
            ")->fetchAll(PDO::FETCH_ASSOC),
            'entradas' => $pdo->query("SELECT * FROM entradas")->fetchAll(PDO::FETCH_ASSOC),
            'ventas' => $pdo->query("
                SELECT evento_id, entrada_id, SUM(cantidad) AS total_vendido
                FROM ventas_entradas
                WHERE estado = 'comprada'
                GROUP BY evento_id, entrada_id
            ")->fetchAll(PDO::FETCH_ASSOC),
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

        $evento = obtenerEventoParaVenta($pdo, $eventoId);
        if (!$evento) {
            jsonResponse(404, [
                'ok' => false,
                'error' => 'El evento no existe, está inactivo o ya fue cerrado.',
            ]);
        }

        $pdo->beginTransaction();
        try {
            if (eventoEstaCerrado($pdo, $eventoId)) {
                $pdo->rollBack();
                jsonResponse(409, [
                    'ok' => false,
                    'error' => 'El evento ya está cerrado.',
                ]);
            }

            $totalesStmt = $pdo->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN estado IN ('comprada', 'usada') THEN cantidad ELSE 0 END), 0) AS total_vendido,
                    COALESCE(SUM(CASE WHEN estado IN ('comprada', 'usada') THEN COALESCE(total, precio_unitario * cantidad) ELSE 0 END), 0) AS total_monto
                FROM ventas_entradas
                WHERE evento_id = :evento_id
            ");
            $totalesStmt->execute([':evento_id' => $eventoId]);
            $totales = $totalesStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_vendido' => 0, 'total_monto' => 0];

            $detalleStmt = $pdo->prepare("
                SELECT
                    ve.entrada_id,
                    COALESCE(en.nombre, CONCAT('Entrada ', ve.entrada_id::text)) AS entrada_nombre,
                    COALESCE(SUM(ve.cantidad), 0) AS cantidad,
                    COALESCE(SUM(COALESCE(ve.total, ve.precio_unitario * ve.cantidad)), 0) AS monto
                FROM ventas_entradas ve
                LEFT JOIN entradas en ON en.id = ve.entrada_id
                WHERE ve.evento_id = :evento_id
                  AND ve.estado IN ('comprada', 'usada')
                GROUP BY ve.entrada_id, entrada_nombre
                ORDER BY entrada_nombre ASC
            ");
            $detalleStmt->execute([':evento_id' => $eventoId]);
            $detalle = $detalleStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $capacidad = (int)($evento['capacidad'] ?? 0);
            $totalVendido = (int)($totales['total_vendido'] ?? 0);
            $porcentaje = $capacidad > 0 ? round(($totalVendido * 100) / $capacidad, 2) : null;

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

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

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

    if (!obtenerEventoParaVenta($pdo, $eventoId)) {
        jsonResponse(409, [
            'ok' => false,
            'error' => 'No se puede vender: el evento no existe, está inactivo o ya fue cerrado.',
        ]);
    }

    $stmtEntrada = $pdo->prepare("
        SELECT id, nombre, precio_base
        FROM entradas
        WHERE id = :id
        LIMIT 1
    ");
    $stmtEntrada->execute([':id' => $entradaId]);
    $entrada = $stmtEntrada->fetch(PDO::FETCH_ASSOC);

    if (!$entrada) {
        jsonResponse(404, [
            'ok' => false,
            'error' => 'Entrada no encontrada',
        ]);
    }

    $precioBase = (float)$entrada['precio_base'];

    $tickets = [];
    $printJobs = [];
    $warnings = [];

    for ($i = 0; $i < $cantidad; $i++) {
        $qr = generarQrCodigo();
        $hash = generarQrHash($qr);

        $sql = "
            INSERT INTO ventas_entradas
                (
                    entrada_id,
                    cantidad,
                    precio_unitario,
                    evento_id,
                    estado,
                    incluye_trago,
                    qr_codigo,
                    qr_hash
                )
            VALUES
                (
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
        ";

        $params = [
            ':entrada_id'     => $entradaId,
            ':precio_unitario' => $precioBase,
            ':evento_id'      => $eventoId,
            ':incluye_trago'  => $trago ? true : false,
            ':qr_codigo'      => $qr,
            ':qr_hash'        => $hash,
        ];

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':entrada_id', $params[':entrada_id'], PDO::PARAM_INT);
        $stmt->bindValue(':precio_unitario', $params[':precio_unitario']);
        $stmt->bindValue(':evento_id', $params[':evento_id'], PDO::PARAM_INT);
        $stmt->bindValue(':incluye_trago', $params[':incluye_trago'], PDO::PARAM_BOOL);
        $stmt->bindValue(':qr_codigo', $params[':qr_codigo'], PDO::PARAM_STR);
        $stmt->bindValue(':qr_hash', $params[':qr_hash'], PDO::PARAM_STR);
        $stmt->execute();

        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        $tickets[] = $ticket;

        $printJobs[] = [
            'ticket_id'       => (int)($ticket['id'] ?? 0),
            'evento_id'       => $eventoId,
            'entrada_id'      => $entradaId,
            'usuario_id'      => $usuarioId > 0 ? $usuarioId : null,
            'tipo'            => (string)$entrada['nombre'],
            'precio'          => (int)round($precioBase),
            'precio_formateado' => number_format($precioBase, 0, ',', '.'),
            'incluye_trago'   => $trago,
            'trago_texto'     => obtenerTextoTrago($trago),
            'qr'              => $qr,
            'estado'          => 'comprada',
            'fecha'           => date('d/m/Y'),
            'hora'            => date('H:i'),
            'negocio'         => 'SANTAS',
            'ancho_papel'     => '80mm',
        ];
    }

    jsonResponse(200, [
        'ok'         => true,
        'tickets'    => $tickets,
        'print_jobs' => $printJobs,
        'warnings'   => $warnings,
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        'ok'       => false,
        'error'    => $e->getMessage(),
        'recovery' => 'Podés reintentar la operación',
    ]);
}
