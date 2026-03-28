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
    $contenido .= str_repeat(" ", (int) floor($espacios)) . $titulo . str_repeat(" ", (int) ceil($espacios)) . "\n";

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

function generarQrCodigo(): string
{
    return strtoupper(bin2hex(random_bytes(16)));
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

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $stmt = $pdo->query(<<<SQL
            SELECT
                v.id,
                v.nombre,
                v.dni,
                v.entrada_id,
                v.evento_id,
                v.promotor_id,
                v.cantidad,
                v.incluye_trago,
                e.nombre AS entrada_nombre,
                v.precio_unitario AS entrada_precio,
                ev.nombre AS evento_nombre
            FROM ventas_entradas v
            LEFT JOIN entradas e ON e.id = v.entrada_id
            LEFT JOIN eventos ev ON ev.id = v.evento_id
            WHERE v.estado = 'comprada'
              AND v.qr_generado_at IS NULL
              AND LOWER(COALESCE(e.nombre, '')) = LOWER('anticipada')
            ORDER BY v.fecha_venta ASC, v.id ASC
        SQL);

        $anticipadas = $stmt->fetchAll();

        foreach ($anticipadas as &$row) {
            $row['id'] = (int) $row['id'];
            $row['entrada_id'] = isset($row['entrada_id']) ? (int) $row['entrada_id'] : null;
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
                : (int) $input['evento_id'];
            $dni = trim($input['dni'] ?? '');
            $cantidad = isset($input['cantidad']) ? max(1, (int) $input['cantidad']) : 1;
            $incluyeTrago = filter_var($input['incluye_trago'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $promotorId = isset($input['promotor_id']) && $input['promotor_id'] !== ''
                ? (int) $input['promotor_id']
                : null;
            $usuarioId = isset($input['usuario_id']) && $input['usuario_id'] !== ''
                ? (int) $input['usuario_id']
                : null;

            if ($nombre === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Debe indicar un nombre.']);
                exit;
            }

            if ($usuarioId === null || $usuarioId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Debe indicar el usuario que realizó la venta.']);
                exit;
            }

            if (!$promotorId) {
                http_response_code(400);
                echo json_encode(['error' => 'Debe indicar un vendedor para registrar la venta.']);
                exit;
            }

            if ($eventoId === null) {
                http_response_code(400);
                echo json_encode(['error' => 'Debe indicar un evento para validar el cupo.']);
                exit;
            }

            $usuarioStmt = $pdo->prepare(<<<SQL
                SELECT
                    u.id,
                    COALESCE(r.nombre, u.rol, '') AS rol_nombre
                FROM usuarios
                u
                LEFT JOIN roles r ON r.id = u.rol_id
                WHERE u.id = :id
                  AND u.activo = true
                LIMIT 1
            SQL);

            $usuarioStmt->execute([':id' => $usuarioId]);
            $usuarioExiste = $usuarioStmt->fetch();

            if (!$usuarioExiste) {
                http_response_code(400);
                echo json_encode(['error' => 'El usuario vendedor indicado no existe o está inactivo.']);
                exit;
            }

            $vendedorStmt = $pdo->prepare(<<<SQL
                SELECT
                    u.id,
                    COALESCE(r.nombre, u.rol, '') AS rol_nombre
                FROM usuarios u
                LEFT JOIN roles r ON r.id = u.rol_id
                WHERE u.id = :id
                  AND u.activo = true
                LIMIT 1
            SQL);
            $vendedorStmt->execute([':id' => $promotorId]);
            $vendedorData = $vendedorStmt->fetch();

            if (!$vendedorData) {
                http_response_code(400);
                echo json_encode(['error' => 'El vendedor seleccionado no existe o está inactivo.']);
                exit;
            }

            $vendedorRol = strtolower(trim((string) ($vendedorData['rol_nombre'] ?? '')));
            $vendedorEsPromotor = in_array($vendedorRol, ['promotor', 'promoter'], true);

            $entradaStmt = $pdo->prepare(<<<SQL
                SELECT id, precio_base, nombre
                FROM entradas
                WHERE LOWER(nombre) = LOWER('anticipada')
                LIMIT 1
            SQL);
            $entradaStmt->execute();
            $entradaData = $entradaStmt->fetch();

            if (!$entradaData) {
                http_response_code(500);
                echo json_encode(['error' => 'No existe una entrada llamada Anticipada.']);
                exit;
            }

            $entradaId = (int) $entradaData['id'];
            $precioUnitario = isset($entradaData['precio_base']) ? (float) $entradaData['precio_base'] : 0.0;

            $pdo->beginTransaction();

            try {
                if ($vendedorEsPromotor) {
                    $cupoStmt = $pdo->prepare(<<<SQL
                        SELECT id, cupo_total, cupo_vendido
                        FROM promotores_cupos
                        WHERE usuario_id = :usuario_id
                          AND evento_id = :evento_id
                          AND entrada_id = :entrada_id
                        ORDER BY id ASC
                        FOR UPDATE
                        LIMIT 1
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
                            RETURNING id, cupo_total, cupo_vendido
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
                        WHERE id = :id
                    SQL);
                    $updateCupoStmt->execute([
                        ':cantidad' => $cantidad,
                        ':id' => $cupo['id'],
                    ]);
                }

                $insertVentaStmt = $pdo->prepare(<<<SQL
                    INSERT INTO ventas_entradas
                    (
                        entrada_id,
                        cantidad,
                        precio_unitario,
                        evento_id,
                        incluye_trago,
                        usuario_id,
                        estado,
                        nombre,
                        dni,
                        promotor_id
                    )
                    VALUES
                    (
                        :entrada_id,
                        :cantidad,
                        :precio_unitario,
                        :evento_id,
                        CAST(:incluye_trago AS boolean),
                        :usuario_id,
                        'comprada',
                        :nombre,
                        :dni,
                        :promotor_id
                    )
                    RETURNING id
                SQL);

                $insertVentaStmt->bindValue(':entrada_id', $entradaId, PDO::PARAM_INT);
                $insertVentaStmt->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
                $insertVentaStmt->bindValue(':precio_unitario', $precioUnitario);
                $insertVentaStmt->bindValue(':incluye_trago', $incluyeTrago ? 'true' : 'false', PDO::PARAM_STR);
                $insertVentaStmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
                $insertVentaStmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
                $insertVentaStmt->bindValue(':dni', $dni !== '' ? $dni : null, $dni !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $insertVentaStmt->bindValue(':promotor_id', $promotorId, PDO::PARAM_INT);

                if ($eventoId === null) {
                    $insertVentaStmt->bindValue(':evento_id', null, PDO::PARAM_NULL);
                } else {
                    $insertVentaStmt->bindValue(':evento_id', $eventoId, PDO::PARAM_INT);
                }

                $insertVentaStmt->execute();
                $nuevoId = (int) $insertVentaStmt->fetchColumn();

                $detalleStmt = $pdo->prepare(<<<SQL
                    SELECT
                        v.id,
                        v.nombre,
                        v.dni,
                        v.entrada_id,
                        v.evento_id,
                        v.promotor_id,
                        v.cantidad,
                        v.incluye_trago,
                        e.nombre AS entrada_nombre,
                        v.precio_unitario AS entrada_precio,
                        ev.nombre AS evento_nombre
                    FROM ventas_entradas v
                    LEFT JOIN entradas e ON e.id = v.entrada_id
                    LEFT JOIN eventos ev ON ev.id = v.evento_id
                    WHERE v.id = :id
                    LIMIT 1
                SQL);
                $detalleStmt->execute([':id' => $nuevoId]);
                $nuevaAnticipada = $detalleStmt->fetch();

                if ($nuevaAnticipada) {
                    $nuevaAnticipada['id'] = (int) $nuevaAnticipada['id'];
                    $nuevaAnticipada['entrada_id'] = isset($nuevaAnticipada['entrada_id']) ? (int) $nuevaAnticipada['entrada_id'] : null;
                    $nuevaAnticipada['cantidad'] = (int) ($nuevaAnticipada['cantidad'] ?? 1);
                    $nuevaAnticipada['incluye_trago'] = filter_var($nuevaAnticipada['incluye_trago'], FILTER_VALIDATE_BOOLEAN);
                    $nuevaAnticipada['entrada_precio'] = isset($nuevaAnticipada['entrada_precio']) ? (float) $nuevaAnticipada['entrada_precio'] : 0.0;
                    $nuevaAnticipada['evento_id'] = isset($nuevaAnticipada['evento_id']) ? (int) $nuevaAnticipada['evento_id'] : null;
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
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                http_response_code(500);
                echo json_encode([
                    'error' => 'Error interno al crear anticipada',
                    'detalle' => $e->getMessage(),
                ]);
                exit;
            }
        }

        if ($accion === 'imprimir') {
            $id = isset($input['id']) ? (int) $input['id'] : null;

            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Debe indicar el ID de la anticipada.']);
                exit;
            }

            $stmt = $pdo->prepare(<<<SQL
                SELECT
                    v.id,
                    v.nombre,
                    v.dni,
                    v.entrada_id,
                    v.evento_id,
                    v.cantidad,
                    v.incluye_trago,
                    v.qr_codigo,
                    e.nombre AS entrada_nombre,
                    v.precio_unitario AS entrada_precio,
                    ev.nombre AS evento_nombre
                FROM ventas_entradas v
                LEFT JOIN entradas e ON e.id = v.entrada_id
                LEFT JOIN eventos ev ON ev.id = v.evento_id
                WHERE v.id = :id
                  AND v.estado = 'comprada'
                LIMIT 1
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

            $qrCodigo = trim((string) ($anticipada['qr_codigo'] ?? ''));
            if ($qrCodigo === '') {
                do {
                    $qrCodigo = generarQrCodigo();

                    $checkQrStmt = $pdo->prepare('
                        SELECT 1
                        FROM ventas_entradas
                        WHERE qr_codigo = :qr_codigo
                        LIMIT 1
                    ');
                    $checkQrStmt->execute([':qr_codigo' => $qrCodigo]);
                    $existeQr = (bool) $checkQrStmt->fetchColumn();
                } while ($existeQr);
            }

            imprimirTickets($nombreEntrada, $precio, $cantidad, $incluyeTrago);

            $updateStmt = $pdo->prepare(<<<SQL
                UPDATE ventas_entradas
                SET
                    qr_codigo = :qr_codigo,
                    qr_generado_at = NOW()
                WHERE id = :id
            SQL);
            $updateStmt->execute([
                ':qr_codigo' => $qrCodigo,
                ':id' => $id,
            ]);

            echo json_encode([
                'success' => true,
                'mensaje' => 'Ticket enviado a impresión y retirado del listado.',
                'id_eliminado' => $id,
                'entrada' => $nombreEntrada,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($accion === 'eliminar') {
            $id = isset($input['id']) ? (int) $input['id'] : null;

            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Debe indicar el ID de la anticipada a eliminar.']);
                exit;
            }

            $stmt = $pdo->prepare(<<<SQL
                UPDATE ventas_entradas
                SET estado = 'anulada'
                WHERE id = :id
                  AND estado = 'comprada'
            SQL);
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'No se encontró el registro solicitado.']);
                exit;
            }

            echo json_encode([
                'success' => true,
                'mensaje' => 'Registro anulado correctamente.',
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
