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
// CONFIGURACIÓN DB
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
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        jsonResponse(400, [
            'ok' => false,
            'error' => 'Solicitud inválida.'
        ]);
    }

    return $data;
}

function normalizeBool($value): bool
{
    return in_array(strtolower(trim((string)$value)), ['1', 'true', 'si', 'sí', 'yes', 'on'], true);
}

function generarQrCodigo(): string
{
    return 'SANTAS-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function generarQrHash(string $qrCodigo): string
{
    return hash('sha256', $qrCodigo);
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

    $stmt = $pdo->query("
        SELECT e.id, e.nombre, e.fecha, e.capacidad, e.activo
        FROM eventos e
        WHERE e.activo = true
          AND e.fecha >= NOW()
          AND NOT EXISTS (
              SELECT 1
              FROM cierres_eventos ce
              WHERE ce.evento_id = e.id
          )
        ORDER BY e.fecha ASC
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

    if (!$entrada) {
        return null;
    }

    if (isset($entrada['activo']) && !filter_var($entrada['activo'], FILTER_VALIDATE_BOOLEAN)) {
        return null;
    }

    return $entrada;
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

    $stmt->bindValue(':entrada_id', $entradaId, PDO::PARAM_INT);
    $stmt->bindValue(':evento_id', $eventoId, PDO::PARAM_INT);
    $stmt->bindValue(':incluye_trago', $incluyeTrago, PDO::PARAM_BOOL);
    $stmt->bindValue(':usuario_id', $usuarioId, $usuarioId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    $stmt->bindValue(':dni', $dni, $dni !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':promotor_id', $promotorId, $promotorId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $stmt->bindValue(':qr_codigo', $qrCodigo, PDO::PARAM_STR);
    $stmt->bindValue(':qr_hash', $qrHash, PDO::PARAM_STR);
    $stmt->bindValue(':observaciones', $observaciones, $observaciones !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->execute();

    return $stmt->fetch();
}

function marcarListaComoImpresaSiAplica(PDO $pdo, ?int $listaId): void
{
    if (!$listaId || $listaId <= 0) {
        return;
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM listas
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $listaId]);
}

function obtenerTextoTrago(bool $incluyeTrago): string
{
    return $incluyeTrago ? 'INCLUYE TRAGO GRATIS' : '';
}

// ======================================
// MAIN
// ======================================
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(405, [
            'ok' => false,
            'error' => 'Método no permitido.'
        ]);
    }

    $pdo = getConnection();
    $input = getJsonInput();

    $nombre = trim((string)($input['nombre'] ?? ''));
    $dni = isset($input['dni']) ? trim((string)$input['dni']) : null;
    $eventoId = isset($input['evento_id']) ? (int)$input['evento_id'] : null;
    $listaId = isset($input['lista_id']) ? (int)$input['lista_id'] : null;
    $usuarioId = isset($input['usuario_id']) && (int)$input['usuario_id'] > 0 ? (int)$input['usuario_id'] : null;
    $promotorId = isset($input['promotor_id']) && (int)$input['promotor_id'] > 0 ? (int)$input['promotor_id'] : null;
    $incluyeTrago = normalizeBool($input['incluye_trago'] ?? false);
    $listaNombre = trim((string)($input['lista'] ?? 'Lista'));
    $observacionesExtra = trim((string)($input['observaciones'] ?? ''));

    if ($nombre === '') {
        $nombre = 'Invitado de lista';
    }

    if ($dni !== null && $dni === '') {
        $dni = null;
    }

    $evento = obtenerEventoActivo($pdo, $eventoId);
    if (!$evento) {
        jsonResponse(404, [
            'ok' => false,
            'error' => 'No se encontró un evento activo válido para asociar la cortesía.'
        ]);
    }

    $entradaGratis = obtenerEntradaGratis($pdo);
    if (!$entradaGratis) {
        jsonResponse(404, [
            'ok' => false,
            'error' => 'No existe una entrada activa llamada Gratis.'
        ]);
    }

    $qrCodigo = generarQrCodigo();
    $qrHash = generarQrHash($qrCodigo);

    $observaciones = 'Cortesía impresa desde lista: ' . $listaNombre;
    if ($observacionesExtra !== '') {
        $observaciones .= ' | ' . $observacionesExtra;
    }

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

    $printJobs = [[
        'ticket_id'           => (int)($venta['id'] ?? 0),
        'venta_id'            => (int)($venta['id'] ?? 0),
        'evento_id'           => (int)$evento['id'],
        'entrada_id'          => (int)$entradaGratis['id'],
        'usuario_id'          => $usuarioId,
        'promotor_id'         => $promotorId,
        'tipo'                => 'CORTESÍA',
        'tipo_detalle'        => (string)$entradaGratis['nombre'],
        'evento_nombre'       => (string)($evento['nombre'] ?? ''),
        'evento_fecha'        => !empty($evento['fecha']) ? date('d/m/Y H:i', strtotime((string)$evento['fecha'])) : '',
        'precio'              => 0,
        'precio_formateado'   => '0',
        'incluye_trago'       => $incluyeTrago,
        'trago_texto'         => obtenerTextoTrago($incluyeTrago),
        'qr'                  => $qrCodigo,
        'estado'              => (string)($venta['estado'] ?? 'comprada'),
        'fecha'               => date('d/m/Y'),
        'hora'                => date('H:i'),
        'negocio'             => 'SANTAS',
        'ancho_papel'         => '80mm',
        'nombre'              => $nombre,
        'dni'                 => $dni,
        'lista'               => $listaNombre,
        'es_cortesia'         => true,
        'observaciones'       => $observaciones,
    ]];

    jsonResponse(200, [
        'ok' => true,
        'mensaje' => 'Cortesía registrada correctamente.',
        'data' => [
            'venta_id'   => (int)$venta['id'],
            'evento_id'  => (int)$evento['id'],
            'entrada_id' => (int)$entradaGratis['id'],
            'estado'     => $venta['estado'],
            'qr_codigo'  => $venta['qr_codigo'],
            'nombre'     => $venta['nombre']
        ],
        'print_jobs' => $printJobs,
        'warnings' => []
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(500, [
        'ok' => false,
        'error' => $e->getMessage(),
        'recovery' => 'Podés reintentar la operación'
    ]);
}
