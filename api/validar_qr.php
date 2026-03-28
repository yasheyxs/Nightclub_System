<?php

declare(strict_types=1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

date_default_timezone_set('America/Argentina/Cordoba');

// ======================================
// CONFIG
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
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        jsonResponse(400, [
            'ok' => false,
            'error' => 'Solicitud inválida.'
        ]);
    }

    return $data;
}

function getClientIp(): ?string
{
    $keys = [
        'HTTP_X_FORWARDED_FOR',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR'
    ];

    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $value = trim((string)$_SERVER[$key]);
            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $parts = explode(',', $value);
                return trim($parts[0]);
            }
            return $value;
        }
    }

    return null;
}

function registrarAccesoQr(
    PDO $pdo,
    int $ventaEntradaId,
    string $qrCodigo,
    ?int $usuarioValidadorId,
    string $resultado,
    ?string $observaciones,
    ?string $dispositivo,
    ?string $ip
): void {
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
        )
        VALUES (
            :venta_entrada_id,
            :qr_codigo,
            NOW(),
            :usuario_validador_id,
            :resultado,
            :observaciones,
            :dispositivo,
            :ip
        )
    ");

    $stmt->execute([
        ':venta_entrada_id' => $ventaEntradaId,
        ':qr_codigo' => $qrCodigo,
        ':usuario_validador_id' => $usuarioValidadorId,
        ':resultado' => $resultado,
        ':observaciones' => $observaciones,
        ':dispositivo' => $dispositivo,
        ':ip' => $ip
    ]);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(405, [
            'ok' => false,
            'error' => 'Método no permitido.'
        ]);
    }

    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password;sslmode=require"
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $input = getJsonInput();

    $qrCodigo = isset($input['qr_codigo']) ? trim((string)$input['qr_codigo']) : '';
    $usuarioValidadorId = isset($input['usuario_validador_id']) ? (int)$input['usuario_validador_id'] : null;
    $dispositivo = isset($input['dispositivo']) ? trim((string)$input['dispositivo']) : null;
    $observaciones = isset($input['observaciones']) ? trim((string)$input['observaciones']) : null;
    $ip = getClientIp();

    if ($qrCodigo === '') {
        jsonResponse(400, [
            'ok' => false,
            'error' => 'El campo qr_codigo es obligatorio.'
        ]);
    }

    $stmt = $pdo->prepare("
        SELECT
            v.id,
            v.entrada_id,
            v.evento_id,
            v.usuario_id,
            v.promotor_id,
            v.estado,
            v.nombre,
            v.dni,
            v.incluye_trago,
            v.fecha_venta,
            v.qr_codigo,
            v.qr_generado_at,
            v.qr_usado_at,
            e.nombre AS entrada_nombre,
            ev.nombre AS evento_nombre,
            ev.activo AS evento_activo
        FROM ventas_entradas v
        LEFT JOIN entradas e ON e.id = v.entrada_id
        LEFT JOIN eventos ev ON ev.id = v.evento_id
        WHERE v.qr_codigo = :qr_codigo
        LIMIT 1
    ");
    $stmt->execute([':qr_codigo' => $qrCodigo]);
    $venta = $stmt->fetch();

    if (!$venta) {
        jsonResponse(404, [
            'ok' => false,
            'resultado' => 'invalido',
            'mensaje' => 'QR inválido. No existe una entrada asociada.'
        ]);
    }

    $ventaId = (int)$venta['id'];
    $estado = strtolower(trim((string)$venta['estado']));

    $pdo->beginTransaction();

    if ($estado === 'anulada') {
        registrarAccesoQr(
            $pdo,
            $ventaId,
            $qrCodigo,
            $usuarioValidadorId,
            'anulado',
            $observaciones ?: 'Intento de ingreso con entrada anulada.',
            $dispositivo,
            $ip
        );

        $pdo->commit();

        jsonResponse(200, [
            'ok' => false,
            'resultado' => 'anulado',
            'mensaje' => 'La entrada está anulada.',
            'data' => [
                'venta_id' => $ventaId,
                'entrada' => $venta['entrada_nombre'],
                'evento' => $venta['evento_nombre'],
                'nombre' => $venta['nombre'],
                'dni' => $venta['dni'],
                'incluye_trago' => filter_var($venta['incluye_trago'], FILTER_VALIDATE_BOOLEAN),
                'estado' => $venta['estado'],
                'qr_usado_at' => $venta['qr_usado_at']
            ]
        ]);
    }

    if ($estado === 'usada') {
        registrarAccesoQr(
            $pdo,
            $ventaId,
            $qrCodigo,
            $usuarioValidadorId,
            'usado',
            $observaciones ?: 'Intento duplicado con entrada ya usada.',
            $dispositivo,
            $ip
        );

        $pdo->commit();

        jsonResponse(200, [
            'ok' => false,
            'resultado' => 'usado',
            'mensaje' => 'La entrada ya fue utilizada.',
            'data' => [
                'venta_id' => $ventaId,
                'entrada' => $venta['entrada_nombre'],
                'evento' => $venta['evento_nombre'],
                'nombre' => $venta['nombre'],
                'dni' => $venta['dni'],
                'incluye_trago' => filter_var($venta['incluye_trago'], FILTER_VALIDATE_BOOLEAN),
                'estado' => $venta['estado'],
                'qr_usado_at' => $venta['qr_usado_at']
            ]
        ]);
    }

    if ($estado !== 'comprada') {
        registrarAccesoQr(
            $pdo,
            $ventaId,
            $qrCodigo,
            $usuarioValidadorId,
            'invalido',
            $observaciones ?: 'Estado no válido para ingreso.',
            $dispositivo,
            $ip
        );

        $pdo->commit();

        jsonResponse(400, [
            'ok' => false,
            'resultado' => 'invalido',
            'mensaje' => 'La entrada no está en un estado válido para ingresar.',
            'data' => [
                'venta_id' => $ventaId,
                'estado' => $venta['estado']
            ]
        ]);
    }

    $update = $pdo->prepare("
        UPDATE ventas_entradas
        SET
            estado = 'usada',
            qr_usado_at = NOW()
        WHERE id = :id
        RETURNING
            id,
            estado,
            qr_usado_at
    ");
    $update->execute([':id' => $ventaId]);
    $updated = $update->fetch();

    registrarAccesoQr(
        $pdo,
        $ventaId,
        $qrCodigo,
        $usuarioValidadorId,
        'valido',
        $observaciones ?: 'Ingreso válido.',
        $dispositivo,
        $ip
    );

    $pdo->commit();

    jsonResponse(200, [
        'ok' => true,
        'resultado' => 'valido',
        'mensaje' => 'Ingreso válido.',
        'data' => [
            'venta_id' => $ventaId,
            'entrada' => $venta['entrada_nombre'],
            'evento' => $venta['evento_nombre'],
            'nombre' => $venta['nombre'],
            'dni' => $venta['dni'],
            'incluye_trago' => filter_var($venta['incluye_trago'], FILTER_VALIDATE_BOOLEAN),
            'estado' => $updated['estado'],
            'fecha_venta' => $venta['fecha_venta'],
            'qr_generado_at' => $venta['qr_generado_at'],
            'qr_usado_at' => $updated['qr_usado_at']
        ]
    ]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(500, [
        'ok' => false,
        'error' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(500, [
        'ok' => false,
        'error' => 'Error inesperado: ' . $e->getMessage()
    ]);
}
