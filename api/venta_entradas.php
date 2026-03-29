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

// ======================================
// MAIN
// ======================================
try {
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        jsonResponse(200, [
            'eventos' => $pdo->query("SELECT * FROM eventos")->fetchAll(PDO::FETCH_ASSOC),
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
