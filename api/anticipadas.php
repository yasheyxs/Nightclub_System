<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

date_default_timezone_set('America/Argentina/Cordoba');

function generarContenidoTicket(string $tipoEntrada, float $monto, bool $incluyeTrago): string
{
    $ancho = 42;

    $contenido = "\n";
    $contenido .= str_repeat("=", 15) . "\n";

    $titulo = "-- SANTAS --";
    $espacios = ($ancho - strlen($titulo)) / 2;
    $contenido .= str_repeat(" ", floor($espacios)) . $titulo . str_repeat(" ", ceil($espacios)) . "\n";

    $contenido .= str_repeat("=", 15) . "\n\n";

    $contenido .= "Entrada: " . $tipoEntrada . "\n";
    $contenido .= "Total: $" . number_format($monto, 0, ',', '.') . "\n";

    if ($incluyeTrago) {
        $contenido .= "INCLUYE TRAGO GRATIS\n";
    }

    $contenido .= "\n" . str_repeat("=", 15) . "\n";
    $contenido .= " Gracias por tu compra \n";
    $contenido .= str_repeat("=", 15) . "\n\n";

    $contenido .= "\n\x1D\x56\x00";

    return $contenido;
}

function enviarAImpresora(string $contenido): void
{
    $archivoTemporal = tempnam(sys_get_temp_dir(), 'ticket_');
    if ($archivoTemporal === false || file_put_contents($archivoTemporal, $contenido) === false) {
        throw new RuntimeException('No se pudo generar el archivo del ticket.');
    }

    $comando = 'cmd.exe /c start /min notepad /p ' . escapeshellarg($archivoTemporal);
    $salida = [];
    $codigo = 0;
    exec($comando, $salida, $codigo);

    @unlink($archivoTemporal);

    if ($codigo !== 0) {
        $mensajeSalida = trim(implode("\n", $salida));
        throw new RuntimeException('Error al enviar el ticket a la impresora: ' . $mensajeSalida);
    }
}

function imprimirTickets(string $tipoEntrada, float $montoUnitario, int $cantidad, bool $incluyeTrago): void
{
    if ($cantidad <= 0) {
        return;
    }

    for ($i = 0; $i < $cantidad; $i++) {
        $contenido = generarContenidoTicket($tipoEntrada, $montoUnitario, $incluyeTrago);
        enviarAImpresora($contenido);
    }
}

$host = "aws-1-us-east-2.pooler.supabase.com";
$port = "5432";
$dbname = "postgres";
$user = "postgres.kxvogvgsgwfvtmidabyp";
$password = "lapicero30!";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password;sslmode=require");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Garantiza la existencia de la tabla
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS anticipadas (
            id SERIAL PRIMARY KEY,
            nombre TEXT NOT NULL,
            dni TEXT,
            entrada_id INTEGER NOT NULL,
            evento_id INTEGER,
            cantidad INTEGER NOT NULL DEFAULT 1,
            incluye_trago BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        );
    SQL);

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $stmt = $pdo->query(<<<SQL
            SELECT
                a.id,
                a.nombre,
                a.dni,
                a.entrada_id,
                a.evento_id,
                a.cantidad,
                a.incluye_trago,
                e.nombre AS entrada_nombre,
                e.precio_base AS entrada_precio,
                ev.nombre AS evento_nombre
            FROM anticipadas a
            LEFT JOIN entradas e ON e.id = a.entrada_id
            LEFT JOIN eventos ev ON ev.id = a.evento_id
            ORDER BY a.created_at ASC, a.id ASC;
        SQL);

        $anticipadas = $stmt->fetchAll();

        foreach ($anticipadas as &$row) {
            $row['id'] = (int) $row['id'];
            $row['entrada_id'] = (int) $row['entrada_id'];
            $row['cantidad'] = (int) ($row['cantidad'] ?? 1);
            $row['incluye_trago'] = filter_var($row['incluye_trago'], FILTER_VALIDATE_BOOLEAN);
            $row['entrada_precio'] = isset($row['entrada_precio']) ? (float) $row['entrada_precio'] : 0.0;
            $row['evento_id'] = isset($row['evento_id']) ? (int) $row['evento_id'] : null;
        }

        echo json_encode($anticipadas, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $accion = $input['accion'] ?? null;

        if ($accion !== 'imprimir') {
            http_response_code(400);
            echo json_encode(['error' => 'Acción no soportada.']);
            exit;
        }

        $id = isset($input['id']) ? (int) $input['id'] : null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Debe indicar el ID de la anticipada.']);
            exit;
        }

        $stmt = $pdo->prepare(<<<SQL
            SELECT
                a.id,
                a.nombre,
                a.dni,
                a.entrada_id,
                a.evento_id,
                a.cantidad,
                a.incluye_trago,
                e.nombre AS entrada_nombre,
                e.precio_base AS entrada_precio,
                ev.nombre AS evento_nombre
            FROM anticipadas a
            LEFT JOIN entradas e ON e.id = a.entrada_id
            LEFT JOIN eventos ev ON ev.id = a.evento_id
            WHERE a.id = :id
            LIMIT 1;
        SQL);
        $stmt->execute([':id' => $id]);
        $anticipada = $stmt->fetch();

        if (!$anticipada) {
            http_response_code(404);
            echo json_encode(['error' => 'No se encontró la entrada anticipada.']);
            exit;
        }

        $nombreEntrada = $anticipada['entrada_nombre'] ?? 'Anticipada';
        $precio = isset($anticipada['entrada_precio']) ? (float) $anticipada['entrada_precio'] : 0.0;
        $cantidad = isset($anticipada['cantidad']) ? (int) $anticipada['cantidad'] : 1;
        $incluyeTrago = filter_var($anticipada['incluye_trago'], FILTER_VALIDATE_BOOLEAN);

        imprimirTickets($nombreEntrada, $precio, $cantidad, $incluyeTrago);

        $deleteStmt = $pdo->prepare('DELETE FROM anticipadas WHERE id = :id');
        $deleteStmt->execute([':id' => $id]);

        echo json_encode([
            'success' => true,
            'mensaje' => 'Ticket enviado a impresión y retirado del listado.',
            'id_eliminado' => $id,
            'entrada' => $nombreEntrada,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error inesperado: ' . $e->getMessage()]);
}
