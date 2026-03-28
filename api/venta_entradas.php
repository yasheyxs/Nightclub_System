<?php

declare(strict_types=1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
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

define('PRINTER_MODE', 'raw'); // raw | notepad
define('PRINTER_TARGET', '\\\\PC-CAJA\\IMPRESORA'); // cambiar por la compartida real

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
        jsonResponse(400, ['error' => 'Solicitud inválida.']);
    }

    return $data;
}

function normalizeBool(mixed $value): bool
{
    if ($value === '' || $value === null) {
        return false;
    }

    if (is_bool($value)) {
        return $value;
    }

    if (is_string($value)) {
        return in_array(strtolower(trim($value)), ['true', '1', 'on', 'yes', 'si'], true);
    }

    if (is_numeric($value)) {
        return ((int)$value) === 1;
    }

    return (bool)$value;
}

function generarQrCodigo(): string
{
    return 'SANTAS-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function generarQrHash(string $qrCodigo): string
{
    return hash('sha256', $qrCodigo);
}

// ======================================
// IMPRESIÓN
// ======================================
function escposCenter(string $text): string
{
    return "\x1B\x61\x01" . $text . "\n" . "\x1B\x61\x00";
}

function escposQr(string $data): string
{
    $storeLen = strlen($data) + 3;
    $pL = chr($storeLen % 256);
    $pH = chr(intdiv($storeLen, 256));

    $cmd = '';
    $cmd .= "\x1D\x28\x6B\x04\x00\x31\x41\x32\x00";
    $cmd .= "\x1D\x28\x6B\x03\x00\x31\x43\x06";
    $cmd .= "\x1D\x28\x6B\x03\x00\x31\x45\x30";
    $cmd .= "\x1D\x28\x6B{$pL}{$pH}\x31\x50\x30" . $data;
    $cmd .= "\x1D\x28\x6B\x03\x00\x31\x51\x30";

    return $cmd;
}

function generarContenidoTicket(string $tipoEntrada, float $montoUnitario, bool $incluyeTrago, string $qrCodigo): string
{
    $out = '';
    $out .= "\x1B\x40";
    $out .= escposCenter('SANTAS');
    $out .= escposCenter('--------------------------');
    $out .= "Entrada: " . $tipoEntrada . "\n";
    $out .= "Total: $" . number_format($montoUnitario, 0, ',', '.') . "\n";

    if ($incluyeTrago) {
        $out .= "INCLUYE TRAGO GRATIS\n";
    }

    $out .= "\n";
    $out .= escposCenter('PRESENTAR QR EN INGRESO');
    $out .= "\n";
    $out .= "\x1B\x61\x01";
    $out .= escposQr($qrCodigo);
    $out .= "\x1B\x61\x00";
    $out .= "\n";
    $out .= escposCenter('Gracias por tu compra');
    $out .= "\n\n\n";
    $out .= "\x1D\x56\x00";

    return $out;
}

function enviarAImpresora(string $contenido): void
{
    $archivoTemporal = tempnam(sys_get_temp_dir(), 'ticket_');
    if ($archivoTemporal === false) {
        throw new RuntimeException('No se pudo crear el archivo temporal.');
    }

    if (file_put_contents($archivoTemporal, $contenido) === false) {
        @unlink($archivoTemporal);
        throw new RuntimeException('No se pudo escribir el ticket temporal.');
    }

    if (PRINTER_MODE === 'raw') {
        $comando = 'cmd.exe /c copy /b ' . escapeshellarg($archivoTemporal) . ' ' . escapeshellarg(PRINTER_TARGET);
    } else {
        $comando = 'cmd.exe /c start /min notepad /p ' . escapeshellarg($archivoTemporal);
    }

    $salida = [];
    $codigo = 0;
    exec($comando, $salida, $codigo);

    @unlink($archivoTemporal);

    if ($codigo !== 0) {
        $mensajeSalida = trim(implode("\n", $salida));
        throw new RuntimeException('Error al enviar el ticket a la impresora: ' . $mensajeSalida);
    }
}

// ======================================
// MAIN
// ======================================
try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password;sslmode=require"
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $eventosStmt = $pdo->query("
            SELECT id, nombre, fecha, capacidad
            FROM eventos
            WHERE activo = true
            ORDER BY fecha ASC
        ");

        $entradasStmt = $pdo->query("
            SELECT id, nombre, descripcion, precio_base
            FROM entradas
            WHERE activo = true
            ORDER BY nombre ASC
        ");

        $ventasStmt = $pdo->query("
            SELECT
                evento_id,
                entrada_id,
                COALESCE(COUNT(*), 0) AS total_vendido
            FROM ventas_entradas
            WHERE estado <> 'anulada'
            GROUP BY evento_id, entrada_id
        ");

        jsonResponse(200, [
            'eventos' => $eventosStmt->fetchAll(),
            'entradas' => $entradasStmt->fetchAll(),
            'ventas' => $ventasStmt->fetchAll()
        ]);
    }

    if ($method === 'POST') {
        $input = getJsonInput();

        if (!isset($input['accion'])) {
            jsonResponse(400, ['error' => 'El campo accion es obligatorio.']);
        }

        $accion = trim((string)$input['accion']);

        // ======================================
        // CERRAR EVENTO
        // ======================================
        if ($accion === 'cerrar_evento') {
            if (!isset($input['evento_id'])) {
                jsonResponse(400, ['error' => 'El campo evento_id es obligatorio.']);
            }

            $eventoId = (int)$input['evento_id'];

            if ($eventoId <= 0) {
                jsonResponse(400, ['error' => 'evento_id inválido.']);
            }

            $eventoStmt = $pdo->prepare("
                SELECT id, nombre, capacidad, activo
                FROM eventos
                WHERE id = :id
                LIMIT 1
            ");
            $eventoStmt->execute([':id' => $eventoId]);
            $evento = $eventoStmt->fetch();

            if (!$evento) {
                jsonResponse(404, ['error' => 'Evento no encontrado.']);
            }

            $estaActivo = isset($evento['activo'])
                ? filter_var($evento['activo'], FILTER_VALIDATE_BOOLEAN)
                : false;

            if (!$estaActivo) {
                jsonResponse(400, ['error' => 'El evento ya está cerrado.']);
            }

            $detalleStmt = $pdo->prepare("
                SELECT
                    e.id AS entrada_id,
                    e.nombre AS entrada_nombre,
                    e.precio_base,
                    COUNT(v.id) FILTER (WHERE v.estado <> 'anulada') AS cantidad,
                    COALESCE(SUM(v.total) FILTER (WHERE v.estado <> 'anulada'), 0) AS total
                FROM entradas e
                LEFT JOIN ventas_entradas v
                    ON v.entrada_id = e.id
                   AND v.evento_id = :evento_id
                WHERE e.activo = true
                GROUP BY e.id, e.nombre, e.precio_base
                HAVING COUNT(v.id) FILTER (WHERE v.estado <> 'anulada') > 0
                ORDER BY e.nombre ASC
            ");
            $detalleStmt->execute([':evento_id' => $eventoId]);
            $detalle = $detalleStmt->fetchAll();

            $totalVendido = 0;
            $totalMonto = 0.0;

            foreach ($detalle as $row) {
                $totalVendido += (int)$row['cantidad'];
                $totalMonto += (float)$row['total'];
            }

            $capacidad = (int)$evento['capacidad'];
            $porcentaje = $capacidad > 0
                ? round(($totalVendido / $capacidad) * 100, 2)
                : 0;

            $detalleJson = json_encode(array_map(function ($row) {
                return [
                    'entrada_id' => (int)$row['entrada_id'],
                    'entrada_nombre' => $row['entrada_nombre'],
                    'precio_base' => (float)$row['precio_base'],
                    'cantidad' => (int)$row['cantidad'],
                    'total' => (float)$row['total']
                ];
            }, $detalle), JSON_UNESCAPED_UNICODE);

            $pdo->beginTransaction();

            $insertCierre = $pdo->prepare("
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
                    CAST(:detalle AS jsonb),
                    NOW()
                )
                RETURNING id, fecha_cierre
            ");

            $insertCierre->execute([
                ':evento_id' => $eventoId,
                ':evento_nombre' => $evento['nombre'],
                ':total_vendido' => $totalVendido,
                ':total_monto' => $totalMonto,
                ':capacidad' => $capacidad,
                ':porcentaje' => $porcentaje,
                ':detalle' => $detalleJson ?: '[]'
            ]);

            $cierre = $insertCierre->fetch();

            $cerrarEventoStmt = $pdo->prepare("
                UPDATE eventos
                SET activo = false
                WHERE id = :id
            ");
            $cerrarEventoStmt->execute([':id' => $eventoId]);

            $pdo->commit();

            jsonResponse(200, [
                'ok' => true,
                'mensaje' => 'Evento cerrado correctamente.',
                'cierre' => [
                    'id' => (int)$cierre['id'],
                    'evento_id' => $eventoId,
                    'evento_nombre' => $evento['nombre'],
                    'fecha_cierre' => $cierre['fecha_cierre'],
                    'total_vendido' => $totalVendido,
                    'total_monto' => $totalMonto,
                    'capacidad' => $capacidad,
                    'porcentaje' => $porcentaje,
                    'detalle' => json_decode($detalleJson ?: '[]', true)
                ]
            ]);
        }

        // ======================================
        // SUMAR / VENDER
        // ======================================
        if ($accion !== 'sumar') {
            jsonResponse(400, ['error' => 'Acción no válida.']);
        }

        if (!isset($input['entrada_id']) || !isset($input['cantidad'])) {
            jsonResponse(400, ['error' => 'Los campos entrada_id y cantidad son obligatorios.']);
        }

        $entradaId = (int)$input['entrada_id'];
        $cantidad = (int)$input['cantidad'];
        $eventoId = isset($input['evento_id']) ? (int)$input['evento_id'] : null;
        $usuarioId = isset($input['usuario_id']) ? (int)$input['usuario_id'] : null;
        $promotorId = isset($input['promotor_id']) ? (int)$input['promotor_id'] : null;
        $nombre = isset($input['nombre']) ? trim((string)$input['nombre']) : null;
        $dni = isset($input['dni']) ? trim((string)$input['dni']) : null;
        $incluyeTrago = normalizeBool($input['incluye_trago'] ?? false);

        if ($cantidad <= 0) {
            jsonResponse(400, ['error' => 'La cantidad debe ser mayor a 0.']);
        }

        if ($eventoId === null || $eventoId <= 0) {
            jsonResponse(400, ['error' => 'El campo evento_id es obligatorio.']);
        }

        $eventoActivoStmt = $pdo->prepare("
            SELECT id, activo
            FROM eventos
            WHERE id = :id
            LIMIT 1
        ");
        $eventoActivoStmt->execute([':id' => $eventoId]);
        $eventoActivo = $eventoActivoStmt->fetch();

        $estaActivo = $eventoActivo && isset($eventoActivo['activo'])
            ? filter_var($eventoActivo['activo'], FILTER_VALIDATE_BOOLEAN)
            : false;

        if (!$estaActivo) {
            jsonResponse(400, ['error' => 'El evento está cerrado o no existe.']);
        }

        $entradaStmt = $pdo->prepare("
            SELECT id, nombre, precio_base
            FROM entradas
            WHERE id = :id AND activo = true
            LIMIT 1
        ");
        $entradaStmt->execute([':id' => $entradaId]);
        $entrada = $entradaStmt->fetch();

        if (!$entrada) {
            jsonResponse(404, ['error' => 'Entrada no encontrada o inactiva.']);
        }

        $nombreEntrada = (string)$entrada['nombre'];
        $precioUnitario = (float)$entrada['precio_base'];

        $promotorResponsableId = null;
        if ($promotorId !== null && $promotorId > 0) {
            $promotorResponsableId = $promotorId;
        } elseif ($usuarioId !== null && $usuarioId > 0) {
            $promotorResponsableId = $usuarioId;
        }

        $insert = $pdo->prepare("
            INSERT INTO ventas_entradas (
                entrada_id,
                cantidad,
                precio_unitario,
                total,
                fecha_venta,
                evento_id,
                incluye_trago,
                usuario_id,
                estado,
                nombre,
                dni,
                promotor_id,
                qr_codigo,
                qr_hash,
                qr_generado_at
            )
            VALUES (
                :entrada_id,
                1,
                :precio_unitario,
                :total,
                NOW(),
                :evento_id,
                :incluye_trago,
                :usuario_id,
                'comprada',
                :nombre,
                :dni,
                :promotor_id,
                :qr_codigo,
                :qr_hash,
                NOW()
            )
            RETURNING
                id,
                entrada_id,
                evento_id,
                cantidad,
                precio_unitario,
                total,
                incluye_trago,
                fecha_venta,
                estado,
                nombre,
                dni,
                promotor_id,
                usuario_id,
                qr_codigo,
                qr_hash,
                qr_generado_at
        ");

        $tickets = [];
        $pdo->beginTransaction();

        try {
            if ($promotorResponsableId !== null) {
                $cupoStmt = $pdo->prepare("
                    SELECT id, cupo_total, cupo_vendido
                    FROM promotores_cupos
                    WHERE usuario_id = :usuario_id
                      AND evento_id = :evento_id
                      AND entrada_id = :entrada_id
                    ORDER BY id ASC
                    FOR UPDATE
                    LIMIT 1
                ");

                $cupoStmt->execute([
                    ':usuario_id' => $promotorResponsableId,
                    ':evento_id' => $eventoId,
                    ':entrada_id' => $entradaId,
                ]);

                $cupo = $cupoStmt->fetch();

                if (!$cupo) {
                    throw new RuntimeException('El promotor no tiene un cupo configurado para esta entrada en este evento.');
                }

                $cupoTotal = (int)$cupo['cupo_total'];
                $cupoVendido = (int)$cupo['cupo_vendido'];
                $cupoDisponible = $cupoTotal - $cupoVendido;

                if ($cupoDisponible < $cantidad) {
                    throw new RuntimeException(
                        'Cupo insuficiente para este promotor. Disponible: ' . $cupoDisponible . '.'
                    );
                }

                $updateCupoStmt = $pdo->prepare("
                    UPDATE promotores_cupos
                    SET cupo_vendido = cupo_vendido + :cantidad
                    WHERE id = :id
                ");

                $updateCupoStmt->execute([
                    ':cantidad' => $cantidad,
                    ':id' => (int)$cupo['id'],
                ]);
            }

            for ($i = 0; $i < $cantidad; $i++) {
                $qrCodigo = generarQrCodigo();
                $qrHash = generarQrHash($qrCodigo);

                $insert->execute([
                    ':entrada_id' => $entradaId,
                    ':precio_unitario' => $precioUnitario,
                    ':total' => $precioUnitario,
                    ':evento_id' => $eventoId,
                    ':incluye_trago' => $incluyeTrago,
                    ':usuario_id' => $usuarioId,
                    ':nombre' => $nombre,
                    ':dni' => $dni,
                    ':promotor_id' => $promotorId,
                    ':qr_codigo' => $qrCodigo,
                    ':qr_hash' => $qrHash
                ]);

                $venta = $insert->fetch();
                $tickets[] = [
                    'id' => (int)$venta['id'],
                    'entrada_id' => (int)$venta['entrada_id'],
                    'evento_id' => $venta['evento_id'] !== null ? (int)$venta['evento_id'] : null,
                    'cantidad' => (int)$venta['cantidad'],
                    'precio_unitario' => (float)$venta['precio_unitario'],
                    'total' => (float)$venta['total'],
                    'incluye_trago' => filter_var($venta['incluye_trago'], FILTER_VALIDATE_BOOLEAN),
                    'fecha_venta' => $venta['fecha_venta'],
                    'estado' => $venta['estado'],
                    'nombre' => $venta['nombre'],
                    'dni' => $venta['dni'],
                    'promotor_id' => $venta['promotor_id'] !== null ? (int)$venta['promotor_id'] : null,
                    'usuario_id' => $venta['usuario_id'] !== null ? (int)$venta['usuario_id'] : null,
                    'qr_codigo' => $venta['qr_codigo'],
                    'qr_hash' => $venta['qr_hash'],
                    'qr_generado_at' => $venta['qr_generado_at']
                ];
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            jsonResponse(400, ['error' => $e->getMessage()]);
        }

        foreach ($tickets as $ticket) {
            $contenido = generarContenidoTicket(
                $nombreEntrada,
                (float)$ticket['precio_unitario'],
                (bool)$ticket['incluye_trago'],
                (string)$ticket['qr_codigo']
            );
            enviarAImpresora($contenido);
        }

        jsonResponse(200, [
            'ok' => true,
            'resumen' => [
                'entrada_id' => $entradaId,
                'evento_id' => $eventoId,
                'cantidad' => count($tickets),
                'precio_unitario' => $precioUnitario,
                'total' => $precioUnitario * count($tickets),
                'incluye_trago' => $incluyeTrago
            ],
            'tickets' => $tickets
        ]);
    }

    jsonResponse(405, ['error' => 'Método no permitido.']);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(500, ['error' => $e->getMessage()]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(500, ['error' => 'Error inesperado: ' . $e->getMessage()]);
}
