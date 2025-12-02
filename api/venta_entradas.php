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
    $ancho = 80; // Ancho para impresora térmica de 80mm
    $linea = str_repeat('-', $ancho); // Línea divisoria
    $fecha = date('d/m/Y H:i');
    $montoFormateado = '$' . number_format($monto, 2, ',', '.');

    // Convertir todo el texto a ISO-8859-1 para evitar problemas con caracteres no soportados
    $tipoEntrada = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $tipoEntrada);
    $fecha = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $fecha);
    $montoFormateado = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $montoFormateado);
    $linea = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $linea);

    // Comando para resetear la impresora a los valores predeterminados
    $resetImpresora = "\x1B\x40";  // Reset

    // Ajustar line spacing al inicio para mayor visibilidad
    $lineSpacingNormal = "\x1B\x33\x30"; // 48 dots de espaciado (ajustable)

    // Comando para cambiar tamaño de fuente a más grande
    $cambiarFuente = "\x1B\x21\x01"; // Fuente más grande

    // Estructura del contenido
    $contenido = [
        $resetImpresora . $cambiarFuente . str_pad('SANTAS', $ancho, ' ', STR_PAD_BOTH),  // Centrado del nombre de la empresa
        $linea,  // Línea divisoria
        str_pad('Entrada: ' . $tipoEntrada, $ancho, ' ', STR_PAD_LEFT),  // Alineación izquierda para tipo de entrada
        str_pad('Fecha: ' . $fecha, $ancho, ' ', STR_PAD_LEFT),  // Alineación izquierda para la fecha
        str_pad('Total: ' . $montoFormateado, $ancho, ' ', STR_PAD_LEFT),  // Alineación izquierda para el total
    ];

    // Agregar el mensaje de "Incluye trago" si corresponde
    if ($incluyeTrago) {
        $contenido[] = str_pad('¡Incluye trago!', $ancho, ' ', STR_PAD_BOTH);  // Centrado del mensaje
    }

    $contenido[] = $linea;  // Línea divisoria final

    // Unir todas las líneas del contenido
    $ticket = implode("\n", $contenido);

    // Agregar comandos de corte y salto de línea
    $comandoCorte = "\n\n" . "\x1D\x56\x00";  // Comando de corte de papel

    // Retornar el contenido completo del ticket
    return $lineSpacingNormal . $ticket . $comandoCorte;
}


function enviarAImpresora(string $contenido): void
{
    $archivoTemporal = tempnam(sys_get_temp_dir(), 'ticket_');

    if ($archivoTemporal === false || file_put_contents($archivoTemporal, $contenido) === false) {
        throw new RuntimeException('No se pudo generar el archivo del ticket.');
    }

    $salida = [];
    $codigo = 0;

    // Usar un comando compatible con el sistema operativo
    if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
        $comando = 'powershell -Command "try { Get-Content -Path ' . escapeshellarg($archivoTemporal) . ' | Out-Printer; exit 0 } catch { Write-Error $_; exit 1 }"';
    } else {
        $comando = 'lpr ' . escapeshellarg($archivoTemporal);  // En sistemas Unix/Linux
    }

    exec($comando, $salida, $codigo);

    @unlink($archivoTemporal);

    if ($codigo !== 0) {
        $mensajeSalida = trim(implode("\n", $salida));
        throw new RuntimeException('Error al enviar el ticket a la impresora. ' . $mensajeSalida);
    }
}

function imprimirTickets(string $tipoEntrada, float $montoUnitario, int $cantidad, bool $incluyeTrago): void
{
    if ($cantidad <= 0) {
        return;
    }

    for ($i = 0; $i < $cantidad; $i++) {
        // Llamar a generarContenidoTicket con 3 argumentos: tipoEntrada, montoUnitario y incluyeTrago
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

    // Asegurar columnas necesarias
    $pdo->exec("ALTER TABLE IF EXISTS public.ventas_entradas ADD COLUMN IF NOT EXISTS evento_id integer");
    $pdo->exec("ALTER TABLE IF EXISTS public.ventas_entradas ADD COLUMN IF NOT EXISTS incluye_trago boolean DEFAULT false");

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

        // Cierre de evento
        if ($accion === 'cerrar_evento') {
            if (!isset($input['evento_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'El campo evento_id es obligatorio para cerrar el evento.']);
                exit;
            }

            $eventoId = (int)$input['evento_id'];
            $eventoStmt = $pdo->prepare("SELECT id, nombre, capacidad FROM eventos WHERE id = :id");
            $eventoStmt->execute([':id' => $eventoId]);
            $evento = $eventoStmt->fetch();

            if (!$evento) {
                http_response_code(404);
                echo json_encode(['error' => 'Evento no encontrado.']);
                exit;
            }

            $detalleStmt = $pdo->prepare("
                SELECT e.id AS entrada_id, e.nombre,
                       COALESCE(SUM(v.cantidad), 0) AS cantidad,
                       COALESCE(SUM(v.total), 0) AS total
                FROM ventas_entradas v
                JOIN entradas e ON e.id = v.entrada_id
                WHERE v.evento_id = :evento_id
                GROUP BY e.id, e.nombre
            ");
            $detalleStmt->execute([':evento_id' => $eventoId]);
            $detalle = $detalleStmt->fetchAll() ?: [];

            $totalEntradas = 0;
            $totalMonto = 0;
            foreach ($detalle as $item) {
                $totalEntradas += (int)$item['cantidad'];
                $totalMonto += (float)$item['total'];
            }

            $capacidad = isset($evento['capacidad']) ? (int)$evento['capacidad'] : null;
            $porcentaje = ($capacidad && $capacidad > 0)
                ? round(($totalEntradas / $capacidad) * 100, 2)
                : null;

            $pdo->exec("CREATE TABLE IF NOT EXISTS public.cierres_eventos (
                id SERIAL PRIMARY KEY,
                evento_id INTEGER,
                evento_nombre TEXT,
                total_vendido INTEGER DEFAULT 0,
                total_monto NUMERIC(12,2) DEFAULT 0,
                capacidad INTEGER,
                porcentaje NUMERIC(6,2),
                detalle JSONB,
                fecha_cierre TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW()
            )");

            $insertCierre = $pdo->prepare("
                INSERT INTO cierres_eventos (evento_id, evento_nombre, total_vendido, total_monto, capacidad, porcentaje, detalle)
                VALUES (:evento_id, :evento_nombre, :total_vendido, :total_monto, :capacidad, :porcentaje, :detalle)
                RETURNING id, fecha_cierre
            ");
            $insertCierre->execute([
                ':evento_id' => $eventoId,
                ':evento_nombre' => $evento['nombre'],
                ':total_vendido' => $totalEntradas,
                ':total_monto' => $totalMonto,
                ':capacidad' => $capacidad,
                ':porcentaje' => $porcentaje,
                ':detalle' => json_encode($detalle, JSON_UNESCAPED_UNICODE)
            ]);

            $cierre = $insertCierre->fetch();

            $pdo->prepare("DELETE FROM ventas_entradas WHERE evento_id = :evento_id")
                ->execute([':evento_id' => $eventoId]);

            $pdo->prepare("UPDATE eventos SET activo = false WHERE id = :id")
                ->execute([':id' => $eventoId]);

            echo json_encode([
                'mensaje' => 'Evento cerrado correctamente.',
                'cierre' => [
                    'id' => (int)$cierre['id'],
                    'evento_id' => $eventoId,
                    'evento_nombre' => $evento['nombre'],
                    'total_vendido' => $totalEntradas,
                    'total_monto' => $totalMonto,
                    'capacidad' => $capacidad,
                    'porcentaje' => $porcentaje,
                    'detalle' => $detalle,
                    'fecha_cierre' => $cierre['fecha_cierre']
                ]
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Registro o resta de venta
        if (!isset($input['entrada_id']) || !isset($input['cantidad'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Los campos entrada_id y cantidad son obligatorios.']);
            exit;
        }

        $entradaId = (int)$input['entrada_id'];
        $cantidad = (int)$input['cantidad'];
        $eventoId = isset($input['evento_id']) ? (int)$input['evento_id'] : null;
        // Normalizar valor booleano sin riesgo de enviar string vacía
        $valor = $input['incluye_trago'] ?? false;

        // Si es string vacía o null -> false
        if ($valor === '' || $valor === null) {
            $incluyeTrago = false;
        }
        // Si viene como texto "true"/"1"/"on"/"yes" -> true
        elseif (is_string($valor)) {
            $incluyeTrago = in_array(strtolower(trim($valor)), ['true', '1', 'on', 'yes'], true);
        }
        // Si viene como número 1/0 -> convertir a bool
        elseif (is_numeric($valor)) {
            $incluyeTrago = ((int)$valor) === 1;
        }
        // Si ya viene boolean -> dejarlo
        else {
            $incluyeTrago = (bool)$valor;
        }

        // ✅ Cast explícito a boolean real para evitar '' en PDO
        $incluyeTrago = $incluyeTrago ? true : false;



        if ($accion === 'restar') $cantidad = -abs($cantidad);
        else $cantidad = abs($cantidad);

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

        // Obtener datos de la entrada
        $entradaStmt = $pdo->prepare("SELECT nombre, precio_base FROM entradas WHERE id = :id AND activo = true");
        $entradaStmt->execute([':id' => $entradaId]);
        $entrada = $entradaStmt->fetch();

        if (!$entrada) {
            http_response_code(404);
            echo json_encode(['error' => 'Entrada no encontrada o inactiva.']);
            exit;
        }

        $nombreEntrada = $entrada['nombre'] ?? 'Entrada';
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

        $controlCode = 'SC-' . $venta['id'] . '-' . substr((string)round(microtime(true) * 1000), -6);

        $ticketsImpresos = 0;

        $incluyeTrago = $venta['incluye_trago'];

        if ($venta['cantidad'] > 0) {
            try {
                // Ahora pasamos 4 argumentos: $nombreEntrada, $precioUnitario, $cantidad, $incluyeTrago
                imprimirTickets($nombreEntrada, $precioUnitario, (int)$venta['cantidad'], $incluyeTrago);
                $ticketsImpresos = (int)$venta['cantidad'];
            } catch (Throwable $e) {
                http_response_code(500);
                echo json_encode([
                    'error' => 'La venta fue registrada pero no se pudieron imprimir los tickets: ' . $e->getMessage(),
                    'id_venta' => (int)$venta['id']
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        echo json_encode([
            'id' => (int)$venta['id'],
            'id_venta' => (int)$venta['id'],
            'entrada_id' => (int)$venta['entrada_id'],
            'evento_id' => $venta['evento_id'] !== null ? (int)$venta['evento_id'] : null,
            'cantidad' => (int)$venta['cantidad'],
            'precio_unitario' => (float)$venta['precio_unitario'],
            'incluye_trago' => (bool)$venta['incluye_trago'],
            'fecha_venta' => $venta['fecha_venta'],
            'total' => (float)$venta['total'],
            'control_code' => $controlCode,
            'tickets_impresos' => $ticketsImpresos,
            'mensaje' => $ticketsImpresos > 0
                ? 'Tickets enviados a la impresora predeterminada.'
                : 'No se generaron tickets para esta operación.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Si el método no es GET ni POST
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error inesperado: ' . $e->getMessage()
    ]);
}
