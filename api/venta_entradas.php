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
// CONFIG DB (dejé lo tuyo igual)
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
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($data) ? $data : [];
}

function normalizeBool($v): bool
{
    return in_array(strtolower((string)$v), ['1', 'true', 'si', 'yes', 'on'], true);
}

function escapeHtml(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function generarQrCodigo(): string
{
    return 'SANTAS-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function generarQrHash(string $qr): string
{
    return hash('sha256', $qr);
}

// ======================================
// QR GENERADOR (SIN LIBRERÍAS PESADAS)
// ======================================
function generarQrBase64(string $qr): array
{
    // 1. Tiny QR (si existe archivo)
    if (file_exists(__DIR__ . '/TinyQRCode.php')) {
        try {
            require_once __DIR__ . '/TinyQRCode.php';

            // Si por alguna razón el archivo no define la clase, creamos un fallback
            // ligero que utiliza la API externa para generar la imagen PNG.
            if (!class_exists('TinyQRCode')) {
                final class TinyQRCode
                {
                    public static function png(string $data, $outfile = null, string $level = 'L', int $size = 5, int $margin = 2)
                    {
                        $url = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=" . urlencode($data);
                        $img = @file_get_contents($url);
                        return $img ?: '';
                    }
                }
            }

            if (class_exists('TinyQRCode')) {
                $img = TinyQRCode::png($qr, null, 'L', 5, 2);
                return [
                    'ok' => true,
                    'base64' => base64_encode($img),
                    'source' => 'tiny'
                ];
            }
        } catch (Throwable $e) {
            // seguimos fallback
        }
    }

    // 2. API externa
    try {
        $url = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=" . urlencode($qr);
        $img = @file_get_contents($url);

        if ($img !== false) {
            return [
                'ok' => true,
                'base64' => base64_encode($img),
                'source' => 'api'
            ];
        }
    } catch (Throwable $e) {
    }

    // 3. fallback texto
    return [
        'ok' => false,
        'base64' => null,
        'source' => 'text'
    ];
}

// ======================================
// HTML TICKET
// ======================================
function generarHtmlTicket(array $data): string
{
    $qrData = generarQrBase64($data['qr']);

    $qrHtml = '';

    if ($qrData['ok']) {
        $qrHtml = '<img src="data:image/png;base64,' . $qrData['base64'] . '" />';
    } else {
        $qrHtml = '<div class="qr-fallback">' . escapeHtml($data['qr']) . '</div>';
    }

    return <<<HTML
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
body { font-family: Arial; width: 80mm; text-align:center; }
.title { font-size:22px; font-weight:bold; }
.qr img { width:220px; }
.qr-fallback { font-size:12px; word-break:break-all; border:1px dashed #000; padding:10px; }
</style>
</head>
<body>

<div class="title">SANTAS</div>

<div>Entrada: {$data['tipo']}</div>
<div>Total: \${$data['precio']}</div>

{$data['trago']}

<div class="qr">$qrHtml</div>

<div>{$data['qr']}</div>

<script>
window.onload = () => window.print();
</script>

</body>
</html>
HTML;
}

// ======================================
// DB
// ======================================
function db(): PDO
{
    global $host, $port, $dbname, $user, $password;

    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password;sslmode=require"
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

// ======================================
// MAIN
// ======================================
try {

    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        jsonResponse(200, [
            'eventos' => $pdo->query("SELECT * FROM eventos")->fetchAll(),
            'entradas' => $pdo->query("SELECT * FROM entradas")->fetchAll()
        ]);
    }

    $input = getJsonInput();

    $entradaId = (int)($input['entrada_id'] ?? 0);
    $cantidad = (int)($input['cantidad'] ?? 1);
    $eventoId = (int)($input['evento_id'] ?? 0);
    $trago = normalizeBool($input['incluye_trago'] ?? false);

    if (!$entradaId || !$eventoId) {
        jsonResponse(400, ['error' => 'Datos inválidos']);
    }

    $entrada = $pdo->query("SELECT * FROM entradas WHERE id = $entradaId")->fetch();

    if (!$entrada) {
        jsonResponse(404, ['error' => 'Entrada no encontrada']);
    }

    $tickets = [];
    $htmls = [];
    $warnings = [];

    for ($i = 0; $i < $cantidad; $i++) {

        $qr = generarQrCodigo();
        $hash = generarQrHash($qr);

        $stmt = $pdo->prepare("
            INSERT INTO ventas_entradas
            (entrada_id, cantidad, precio_unitario, evento_id, estado, qr_codigo, qr_hash)
            VALUES (:e,1,:p,:ev,'comprada',:qr,:h)
            RETURNING *
        ");

        $stmt->execute([
            ':e' => $entradaId,
            ':p' => $entrada['precio_base'],
            ':ev' => $eventoId,
            ':qr' => $qr,
            ':h' => $hash
        ]);

        $ticket = $stmt->fetch();

        $tickets[] = $ticket;

        $html = generarHtmlTicket([
            'tipo' => escapeHtml($entrada['nombre']),
            'precio' => number_format($entrada['precio_base'], 0, ',', '.'),
            'trago' => $trago ? '<div>INCLUYE TRAGO GRATIS</div>' : '',
            'qr' => $qr
        ]);

        $htmls[] = [
            'ticket_id' => $ticket['id'],
            'html' => $html
        ];
    }

    jsonResponse(200, [
        'ok' => true,
        'tickets' => $tickets,
        'html_tickets' => $htmls,
        'warnings' => $warnings
    ]);
} catch (Throwable $e) {

    jsonResponse(500, [
        'ok' => false,
        'error' => $e->getMessage(),
        'recovery' => 'Podés reintentar la operación'
    ]);
}
