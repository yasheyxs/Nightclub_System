<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
            promotor_id INTEGER,
            cantidad INTEGER NOT NULL DEFAULT 1,
            incluye_trago BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        );
    SQL);
    $pdo->exec("ALTER TABLE anticipadas ADD COLUMN IF NOT EXISTS promotor_id INTEGER");

    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS promotores_cupos (
            id SERIAL PRIMARY KEY,
            usuario_id INTEGER NOT NULL,
            evento_id INTEGER NOT NULL,
            entrada_id INTEGER NOT NULL,
            cupo_total INTEGER NOT NULL DEFAULT 50,
            cupo_vendido INTEGER NOT NULL DEFAULT 0,
            fecha_creacion TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW()
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
                a.promotor_id,
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
            $row['promotor_id'] = isset($row['promotor_id']) ? (int) $row['promotor_id'] : null;
        }

        echo json_encode($anticipadas, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $accion = $input['accion'] ?? 'crear';

        if ($accion === 'crear') {
            $nombre = trim($input['nombre'] ?? '');
            $eventoId = ($input['evento_id'] === "" || $input['evento_id'] === null)
                ? null
                : (int)$input['evento_id'];
            $dni = trim($input['dni'] ?? '');
            $cantidad = isset($input['cantidad']) ? max(1, (int)$input['cantidad']) : 1;
            $incluyeTrago = filter_var($input['incluye_trago'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $incluyeTragoValor = $incluyeTrago ? 'true' : 'false';
            $promotorId = isset($input['promotor_id']) ? (int) $input['promotor_id'] : null;

            if ($nombre === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Debe indicar un nombre.']);
                exit;
            }

            if (!$promotorId) {
                http_response_code(400);
                echo json_encode(['error' => 'Debe indicar un promotor para asignar el cupo.']);
                exit;
            }

            if ($eventoId === null) {
                http_response_code(400);
                echo json_encode(['error' => 'Debe indicar un evento para validar el cupo.']);
                exit;
            }

            // BUSCAR AUTOMÁTICAMENTE LA ENTRADA "ANTICIPADA"
            $entradaStmt = $pdo->prepare("
                SELECT id, precio_base
                FROM entradas
                WHERE LOWER(nombre) = LOWER('anticipada')
                LIMIT 1
            ");
            $entradaStmt->execute();
            $entradaData = $entradaStmt->fetch();

            if (!$entradaData) {
                http_response_code(500);
                echo json_encode(['error' => 'No existe una entrada llamada Anticipada.']);
                exit;
            }

            $entradaId = (int)$entradaData['id'];
            $precioUnitario = isset($entradaData['precio_base']) ? (float)$entradaData['precio_base'] : 0.0;

            $pdo->beginTransaction();

            try {
                $cupoStmt = $pdo->prepare(<<<SQL
                    SELECT id, cupo_total, cupo_vendido
                    FROM promotores_cupos
                    WHERE usuario_id = :usuario_id
                      AND evento_id = :evento_id
                      AND entrada_id = :entrada_id
                    ORDER BY id ASC
                    FOR UPDATE
                    LIMIT 1;
                SQL);
                $cupoStmt->execute([
                    ':usuario_id' => $promotorId,
                    ':evento_id' => $eventoId,
                    ':entrada_id' => $entradaId,
                ]);
                $cupo = $cupoStmt->fetch();

                if (!$cupo) {
                    $insertCupoStmt = $pdo->prepare(<<<SQL
                        INSERT INTO promotores_cupos (usuario_id, evento_id, entrada_id)
                        VALUES (:usuario_id, :evento_id, :entrada_id)
                        RETURNING id, cupo_total, cupo_vendido;
                    SQL);
                    $insertCupoStmt->execute([
                        ':usuario_id' => $promotorId,
                        ':evento_id' => $eventoId,
                        ':entrada_id' => $entradaId,
                    ]);
                    $cupo = $insertCupoStmt->fetch();
                }

                $cupoTotal = isset($cupo['cupo_total']) ? (int) $cupo['cupo_total'] : 0;
                $cupoVendido = isset($cupo['cupo_vendido']) ? (int) $cupo['cupo_vendido'] : 0;
                $cupoDisponible = $cupoTotal - $cupoVendido;

                if ($cantidad > $cupoDisponible) {
                    $pdo->rollBack();
                    http_response_code(409);
                    echo json_encode([
                        'error' => 'No hay cupo suficiente para esta venta.',
                        'cupo_disponible' => $cupoDisponible,
                    ]);
                    exit;
                }

                $updateCupoStmt = $pdo->prepare(<<<SQL
                    UPDATE promotores_cupos
                    SET cupo_vendido = cupo_vendido + :cantidad
                    WHERE id = :id;
                SQL);
                $updateCupoStmt->execute([
                    ':cantidad' => $cantidad,
                    ':id' => $cupo['id'],
                ]);

                $stmt = $pdo->prepare(<<<SQL
                    INSERT INTO anticipadas (nombre, dni, entrada_id, evento_id, promotor_id, cantidad, incluye_trago)
                    VALUES (:nombre, :dni, :entrada_id, :evento_id, :promotor_id, :cantidad, CAST(:incluye_trago AS boolean))
                    RETURNING id;
                SQL);

                $stmt->execute([
                    ':nombre' => $nombre,
                    ':dni' => $dni ?: null,
                    ':entrada_id' => $entradaId,
                    ':evento_id' => $eventoId,
                    ':promotor_id' => $promotorId,
                    ':cantidad' => $cantidad,
                    ':incluye_trago' => $incluyeTragoValor,
                ]);

                $nuevoId = (int)$stmt->fetchColumn();

                $ventaStmt = $pdo->prepare('
                    INSERT INTO ventas_entradas (entrada_id, evento_id, cantidad, precio_unitario, incluye_trago)
                    VALUES (:entrada_id, :evento_id, :cantidad, :precio_unitario, CAST(:incluye_trago AS boolean))
                ');
                $ventaStmt->bindValue(':entrada_id', $entradaId, PDO::PARAM_INT);
                if ($eventoId === null) {
                    $ventaStmt->bindValue(':evento_id', null, PDO::PARAM_NULL);
                } else {
                    $ventaStmt->bindValue(':evento_id', $eventoId, PDO::PARAM_INT);
                }
                $ventaStmt->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
                $ventaStmt->bindValue(':precio_unitario', $precioUnitario);
                $ventaStmt->bindValue(':incluye_trago', $incluyeTragoValor, PDO::PARAM_STR);
                $ventaStmt->execute();

                $detalleStmt = $pdo->prepare(<<<SQL
                    SELECT
                        a.id,
                        a.nombre,
                        a.dni,
                        a.entrada_id,
                        a.evento_id,
                        a.promotor_id,
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
                $detalleStmt->execute([':id' => $nuevoId]);

                $nuevaAnticipada = $detalleStmt->fetch();

                if ($nuevaAnticipada) {
                    $nuevaAnticipada['id'] = (int)$nuevaAnticipada['id'];
                    $nuevaAnticipada['entrada_id'] = (int)$nuevaAnticipada['entrada_id'];
                    $nuevaAnticipada['cantidad'] = (int)($nuevaAnticipada['cantidad'] ?? 1);
                    $nuevaAnticipada['incluye_trago'] = filter_var($nuevaAnticipada['incluye_trago'], FILTER_VALIDATE_BOOLEAN);
                    $nuevaAnticipada['entrada_precio'] = isset($nuevaAnticipada['entrada_precio']) ? (float)$nuevaAnticipada['entrada_precio'] : 0.0;
                    $nuevaAnticipada['evento_id'] = isset($nuevaAnticipada['evento_id']) ? (int)$nuevaAnticipada['evento_id'] : null;
                    $nuevaAnticipada['promotor_id'] = isset($nuevaAnticipada['promotor_id']) ? (int) $nuevaAnticipada['promotor_id'] : null;
                }

                $pdo->commit();

                echo json_encode([
                    'success' => true,
                    'mensaje' => 'Anticipada registrada correctamente.',
                    'anticipada' => $nuevaAnticipada,
                ], JSON_UNESCAPED_UNICODE);
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();

                http_response_code(500);
                echo json_encode([
                    'error' => 'Error interno al crear anticipada',
                    'detalle' => $e->getMessage(),
                ]);
                exit;
            }
        }

        if ($accion === 'imprimir') {
            $id = isset($input['id']) ? (int)$input['id'] : null;
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
            $precio = isset($anticipada['entrada_precio']) ? (float)$anticipada['entrada_precio'] : 0.0;
            $cantidad = isset($anticipada['cantidad']) ? (int)$anticipada['cantidad'] : 1;
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

        if ($accion === 'eliminar') {
            $id = isset($input['id']) ? (int)$input['id'] : null;

            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Debe indicar el ID de la anticipada a eliminar.']);
                exit;
            }

            $deleteStmt = $pdo->prepare('DELETE FROM anticipadas WHERE id = :id');
            $deleteStmt->execute([':id' => $id]);

            if ($deleteStmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'No se encontró el registro solicitado.']);
                exit;
            }

            echo json_encode([
                'success' => true,
                'mensaje' => 'Registro eliminado correctamente.',
                'id_eliminado' => $id,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        http_response_code(400);
        echo json_encode(['error' => 'Acción no soportada.']);
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
