<?php

declare(strict_types=1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=utf-8");

date_default_timezone_set('America/Argentina/Cordoba');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ======================================
// CONFIGURACIÓN
// ======================================
$host = "aws-1-us-east-2.pooler.supabase.com";
$port = "5432";
$dbname = "postgres";
$user = "postgres.kxvogvgsgwfvtmidabyp";
$password = "lapicero30!";

// IMPRESORA
// Cambiá esto por el nombre compartido real de tu impresora si querés impresión RAW.
// Ej: \\PC-CAJA\\EPSON
define('PRINTER_TARGET', '\\\\PC-CAJA\\IMPRESORA');
define('PRINTER_MODE', 'raw'); // raw | notepad

// ======================================
// HELPERS
// ======================================
function jsonResponse(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getConnection(): PDO
{
    global $host, $port, $dbname, $user, $password;

    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password;sslmode=require"
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    return $pdo;
}

function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        jsonResponse(400, ['ok' => false, 'error' => 'Solicitud inválida.']);
    }

    return $data;
}

function generarQrCodigo(): string
{
    return 'SANTAS-' . date('YmdHis') . '-' . bin2hex(random_bytes(5));
}

function generarQrHash(string $qrCodigo): string
{
    return hash('sha256', $qrCodigo);
}

function obtenerEventoActivo(PDO $pdo, ?int $eventoId = null): ?array
{
    if ($eventoId !== null && $eventoId > 0) {
        $stmt = $pdo->prepare("
            SELECT id, nombre, fecha, capacidad, activo
            FROM eventos
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $eventoId]);
        $evento = $stmt->fetch();

        return $evento ?: null;
    }

    $stmt = $pdo->query("
        SELECT id, nombre, fecha, capacidad, activo
        FROM eventos
        WHERE activo = true
          AND fecha >= NOW()
        ORDER BY fecha ASC
        LIMIT 1
    ");

    $evento = $stmt->fetch();
    return $evento ?: null;
}

function obtenerEntradaGratis(PDO $pdo): ?array
{
    $stmt = $pdo->query("
        SELECT id, nombre, precio_base, activo
        FROM entradas
        WHERE LOWER(TRIM(nombre)) = 'gratis'
        LIMIT 1
    ");

    $entrada = $stmt->fetch();
    return $entrada ?: null;
}

function registrarVentaCortesia(
    PDO $pdo,
    int $entradaId,
    int $eventoId,
    string $nombre,
    ?string $dni,
    bool $incluyeTrago,
    ?int $usuarioId,
    ?int $promotorId,
    string $qrCodigo,
    string $qrHash,
    ?string $observaciones
): array {
    $stmt = $pdo->prepare("
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
            qr_generado_at,
            observaciones
        )
        VALUES (
            :entrada_id,
            1,
            0,
            0,
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
            NOW(),
            :observaciones
        )
        RETURNING *
    ");

    $stmt->execute([
        ':entrada_id' => $entradaId,
        ':evento_id' => $eventoId,
        ':incluye_trago' => $incluyeTrago,
        ':usuario_id' => $usuarioId,
        ':nombre' => $nombre,
        ':dni' => $dni,
        ':promotor_id' => $promotorId,
        ':qr_codigo' => $qrCodigo,
        ':qr_hash' => $qrHash,
        ':observaciones' => $observaciones
    ]);

    return $stmt->fetch();
}

function marcarListaComoImpresaSiAplica(PDO $pdo, ?int $listaId): void
{
    if (!$listaId || $listaId <= 0) {
        return;
    }

    // No marcamos ingreso=true acá.
    // Eso debe ocurrir cuando efectivamente se valida el QR en puerta.
    $stmt = $pdo->prepare("
        SELECT id
        FROM listas
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $listaId]);
}

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
    $cmd .= "\x1D\x28\x6B\x04\x00\x31\x41\x32\x00";          // Model 2
    $cmd .= "\x1D\x28\x6B\x03\x00\x31\x43\x06";              // Size
    $cmd .= "\x1D\x28\x6B\x03\x00\x31\x45\x30";              // Error correction
    $cmd .= "\x1D\x28\x6B{$pL}{$pH}\x31\x50\x30" . $data;    // Store
    $cmd .= "\x1D\x28\x6B\x03\x00\x31\x51\x30";              // Print

    return $cmd;
}

function generarContenidoTicket(array $venta, array $evento, string $tipoEntrada): string
{
    $nombre = trim((string)($venta['nombre'] ?? ''));
    $dni = trim((string)($venta['dni'] ?? ''));
    $incluyeTrago = (bool)($venta['incluye_trago'] ?? false);
    $qrCodigo = (string)($venta['qr_codigo'] ?? '');
    $fechaEvento = isset($evento['fecha']) ? date('d/m/Y H:i', strtotime((string)$evento['fecha'])) : '';
    $eventoNombre = (string)($evento['nombre'] ?? '');

    $out = '';

    $out .= "\x1B\x40";
    $out .= escposCenter('SANTAS');
    $out .= escposCenter('--------------------------');
    $out .= "Tipo: " . $tipoEntrada . "\n";
    $out .= "Fecha: " . $fechaEvento . "\n";
    $out .= "Nombre: " . ($nombre !== '' ? $nombre : 'Invitado') . "\n";

    if ($dni !== '') {
        $out .= "DNI: " . $dni . "\n";
    }

    $out .= "Total: $0\n";

    if ($incluyeTrago) {
        $out .= "Incluye trago: SI\n";
    }

    $out .= "QR: " . $qrCodigo . "\n";
    $out .= "\n";
    $out .= escposCenter('PRESENTAR QR EN INGRESO');
    $out .= "\n";
    $out .= "\x1B\x61\x01";
    $out .= escposQr($qrCodigo);
    $out .= "\x1B\x61\x00";
    $out .= "\n";
    $out .= escposCenter('Gracias por venir');
    $out .= "\n\n\n";
    $out .= "\x1D\x56\x00";

    return $out;
}

function enviarAImpresora(string $contenido): void
{
    $archivoTemporal = tempnam(sys_get_temp_dir(), 'ticket_');
    if ($archivoTemporal === false) {
        throw new RuntimeException('No se pudo crear archivo temporal.');
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
        $mensaje = trim(implode("\n", $salida));
        throw new RuntimeException('Error al imprimir ticket. ' . $mensaje);
    }
}

// ======================================
// MAIN
// ======================================
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(405, ['ok' => false, 'error' => 'Método no permitido.']);
    }

    $pdo = getConnection();
    $input = getJsonInput();

    $nombre = isset($input['nombre']) ? trim((string)$input['nombre']) : '';
    $dni = isset($input['dni']) ? trim((string)$input['dni']) : null;
    $eventoId = isset($input['evento_id']) ? (int)$input['evento_id'] : null;
    $listaId = isset($input['lista_id']) ? (int)$input['lista_id'] : null;
    $usuarioId = isset($input['usuario_id']) ? (int)$input['usuario_id'] : null;
    $promotorId = isset($input['promotor_id']) ? (int)$input['promotor_id'] : null;
    $incluyeTrago = isset($input['incluye_trago']) ? (bool)$input['incluye_trago'] : false;
    $listaNombre = isset($input['lista']) ? trim((string)$input['lista']) : 'Lista';
    $observaciones = 'Cortesía impresa desde lista: ' . $listaNombre;

    if ($nombre === '') {
        $nombre = 'Invitado de lista';
    }

    $evento = obtenerEventoActivo($pdo, $eventoId);
    if (!$evento) {
        jsonResponse(404, [
            'ok' => false,
            'error' => 'No se encontró un evento activo para asociar la cortesía.'
        ]);
    }

    $entradaGratis = obtenerEntradaGratis($pdo);
    if (!$entradaGratis) {
        jsonResponse(404, [
            'ok' => false,
            'error' => 'No existe una entrada llamada Gratis.'
        ]);
    }

    $qrCodigo = generarQrCodigo();
    $qrHash = generarQrHash($qrCodigo);

    $pdo->beginTransaction();

    $venta = registrarVentaCortesia(
        $pdo,
        (int)$entradaGratis['id'],
        (int)$evento['id'],
        $nombre,
        $dni,
        $incluyeTrago,
        $usuarioId,
        $promotorId,
        $qrCodigo,
        $qrHash,
        $observaciones
    );

    marcarListaComoImpresaSiAplica($pdo, $listaId);

    $pdo->commit();

    $contenido = generarContenidoTicket($venta, $evento, (string)$entradaGratis['nombre']);
    enviarAImpresora($contenido);

    jsonResponse(200, [
        'ok' => true,
        'mensaje' => 'Ticket de cortesía impreso correctamente.',
        'data' => [
            'venta_id' => (int)$venta['id'],
            'evento_id' => (int)$evento['id'],
            'entrada_id' => (int)$entradaGratis['id'],
            'estado' => $venta['estado'],
            'qr_codigo' => $venta['qr_codigo'],
            'nombre' => $venta['nombre']
        ]
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(500, [
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
