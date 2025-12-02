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

// Función para generar contenido del ticket
function generarContenidoTicket(string $tipoEntrada, float $monto, bool $incluyeTrago): string
{
    // Definir el ancho de la línea para centrar el título "SANTAS"
    $ancho = 42;

    // Crear el contenido del ticket en formato UTF-8
    $contenido = "\n";
    $contenido .= str_repeat("=", 15) . "\n";  // Línea superior de "="

    // Centrar "SANTAS" en el centro de una línea de $ancho caracteres
    $titulo = "-- SANTAS --";
    $espacios = ($ancho - strlen($titulo)) / 2;  // Calcular los espacios antes de "SANTAS"
    $contenido .= str_repeat(" ", floor($espacios)) . $titulo . str_repeat(" ", ceil($espacios)) . "\n";

    $contenido .= str_repeat("=", 15) . "\n\n";  // Línea inferior de "="

    $contenido .= "Entrada: " . $tipoEntrada . "\n";  // Entrada al lado de "Entrada:"
    $contenido .= "Total: $" . number_format($monto, 0, ',', '.') . "\n";  // Total al lado de "Total:", sin decimales

    // Si incluye trago, añadir la línea correspondiente
    if ($incluyeTrago) {
        $contenido .= "INCLUYE TRAGO GRATIS\n";  // Esta línea solo se imprime si $incluyeTrago es true
    }

    $contenido .= "\n" . str_repeat("=", 15) . "\n";  // Línea para separar
    $contenido .= " Gracias por tu compra \n";  // Mensaje de agradecimiento
    $contenido .= str_repeat("=", 15) . "\n\n";  // Línea final de "="

    // Comando de corte (específico para tu impresora)
    $contenido .= "\n\x1D\x56\x00";  // Comando ESC/POS para corte

    // Devolver el contenido en UTF-8, sin usar utf8_encode (ya es UTF-8)
    return $contenido;
}

// Función para enviar el contenido a la impresora
function enviarAImpresora(string $contenido): void
{
    // Crear un archivo temporal con el contenido en UTF-8
    $archivoTemporal = tempnam(sys_get_temp_dir(), 'ticket_');
    if ($archivoTemporal === false || file_put_contents($archivoTemporal, $contenido) === false) {
        throw new RuntimeException('No se pudo generar el archivo del ticket.');
    }

    // Comando para enviar el archivo a la impresora (sin proceso intermedio)
    $comando = 'cmd.exe /c start /min notepad /p ' . escapeshellarg($archivoTemporal);

    // Ejecutar el comando para imprimir
    $salida = [];
    $codigo = 0;
    exec($comando, $salida, $codigo);

    // Eliminar el archivo temporal inmediatamente después de enviarlo
    @unlink($archivoTemporal);

    // Verificar si hubo algún error al enviar el comando
    if ($codigo !== 0) {
        $mensajeSalida = trim(implode("\n", $salida));
        throw new RuntimeException('Error al enviar el ticket a la impresora: ' . $mensajeSalida);
    }
}

// Función para imprimir varios tickets
function imprimirTickets(string $tipoEntrada, float $montoUnitario, int $cantidad, bool $incluyeTrago): void
{
    if ($cantidad <= 0) {
        return;
    }
    for ($i = 0; $i < $cantidad; $i++) {
        // Generar el contenido del ticket
        $contenido = generarContenidoTicket($tipoEntrada, $montoUnitario, $incluyeTrago);

        // Enviar el contenido a la impresora
        enviarAImpresora($contenido);
    }
}

$host = "aws-1-us-east-2.pooler.supabase.com";
$port = "5432";
$dbname = "postgres";
$user = "postgres.kxvogvgsgwfvtmidabyp";
$password = "lapicero30!";

try {
    // Conexión a la base de datos
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password;sslmode=require");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $eventosStmt = $pdo->query("SELECT id, nombre, fecha, capacidad FROM eventos WHERE activo = true ORDER BY fecha ASC");
        $entradasStmt = $pdo->query("SELECT id, nombre, descripcion, precio_base FROM entradas WHERE activo = true ORDER BY nombre ASC");
        $ventasStmt = $pdo->query("SELECT evento_id, entrada_id, COALESCE(SUM(cantidad), 0) AS total_vendido FROM ventas_entradas GROUP BY evento_id, entrada_id");

        echo json_encode([
            'eventos' => $eventosStmt->fetchAll(),
            'entradas' => $entradasStmt->fetchAll(),
            'ventas' => $ventasStmt->fetchAll()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['accion'])) {
            http_response_code(400);
            echo json_encode(['error' => 'El campo accion es obligatorio.']);
            exit;
        }

        $accion = $input['accion'];

        // Registro o resta de venta
        if (!isset($input['entrada_id']) || !isset($input['cantidad'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Los campos entrada_id y cantidad son obligatorios.']);
            exit;
        }

        $entradaId = (int)$input['entrada_id'];
        $cantidad = (int)$input['cantidad'];
        $eventoId = isset($input['evento_id']) ? (int)$input['evento_id'] : null;
        $valor = $input['incluye_trago'] ?? false;

        // Normalizar valor booleano
        if ($valor === '' || $valor === null) {
            $incluyeTrago = false;
        } elseif (is_string($valor)) {
            $incluyeTrago = in_array(strtolower(trim($valor)), ['true', '1', 'on', 'yes'], true);
        } elseif (is_numeric($valor)) {
            $incluyeTrago = ((int)$valor) === 1;
        } else {
            $incluyeTrago = (bool)$valor;
        }

        // Verificar si el evento está activo
        if ($eventoId !== null) {
            $eventoActivoStmt = $pdo->prepare("SELECT activo FROM eventos WHERE id = :id");
            $eventoActivoStmt->execute([':id' => $eventoId]);
            $eventoActivo = $eventoActivoStmt->fetch();

            $estaActivo = $eventoActivo && isset($eventoActivo['activo'])
                ? filter_var($eventoActivo['activo'], FILTER_VALIDATE_BOOLEAN)
                : false;

            if (!$estaActivo) {
                http_response_code(400);
                echo json_encode(['error' => 'El evento está cerrado o no existe.']);
                exit;
            }
        }

        // Obtener precio base de la entrada
        $entradaStmt = $pdo->prepare("SELECT precio_base FROM entradas WHERE id = :id AND activo = true");
        $entradaStmt->execute([':id' => $entradaId]);
        $entrada = $entradaStmt->fetch();

        if (!$entrada) {
            http_response_code(404);
            echo json_encode(['error' => 'Entrada no encontrada o inactiva.']);
            exit;
        }

        $precioUnitario = (float)$entrada['precio_base'];

        // Insertar venta
        $insert = $pdo->prepare("
            INSERT INTO ventas_entradas (entrada_id, evento_id, cantidad, precio_unitario, incluye_trago)
            VALUES (:entrada_id, :evento_id, :cantidad, :precio_unitario, CAST(:incluye_trago AS boolean))
            RETURNING id, entrada_id, evento_id, cantidad, precio_unitario, incluye_trago, fecha_venta
        ");

        $insert->bindValue(':entrada_id', $entradaId, PDO::PARAM_INT);
        $insert->bindValue(':evento_id', $eventoId, PDO::PARAM_INT);
        $insert->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
        $insert->bindValue(':precio_unitario', $precioUnitario);
        $insert->bindValue(':incluye_trago', $incluyeTrago ? 'true' : 'false', PDO::PARAM_STR);
        $insert->execute();

        $venta = $insert->fetch();
        $venta['total'] = (float)$venta['precio_unitario'] * (int)$venta['cantidad'];

        // Imprimir el ticket de la venta
        imprimirTickets("Venta Entrada ID: " . $venta['entrada_id'], $venta['total'], $cantidad, $incluyeTrago);

        echo json_encode([
            'id' => (int)$venta['id'],
            'entrada_id' => (int)$venta['entrada_id'],
            'evento_id' => $venta['evento_id'] !== null ? (int)$venta['evento_id'] : null,
            'cantidad' => (int)$venta['cantidad'],
            'precio_unitario' => (float)$venta['precio_unitario'],
            'incluye_trago' => (bool)$venta['incluye_trago'],
            'fecha_venta' => $venta['fecha_venta'],
            'total' => (float)$venta['total']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Si el método no es GET ni POST
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error inesperado: ' . $e->getMessage()]);
}
