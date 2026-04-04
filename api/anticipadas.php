<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=utf-8");

ini_set('display_errors', '0');
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

date_default_timezone_set('America/Argentina/Cordoba');

function logTime(string $label, float $start): void
{
    error_log($label . ': ' . round((microtime(true) - $start) * 1000, 2) . ' ms');
}

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

function normalizeBool(mixed $value): bool
{
    return in_array(
        strtolower(trim((string)$value)),
        ['1', 'true', 'si', 'yes', 'on'],
        true
    );
}

function generarQrHash(string $qr): string
{
    return hash('sha256', $qr);
}

function generarQrCodigo(): string
{
    return strtoupper(bin2hex(random_bytes(16)));
}

function generarQrUnico(PDO $pdo): string
{
    $checkQrStmt = $pdo->prepare("
        SELECT 1
        FROM ventas_entradas
        WHERE qr_codigo = :qr_codigo
        LIMIT 1
    ");

    do {
        $qrCodigo = generarQrCodigo();
        $checkQrStmt->execute([':qr_codigo' => $qrCodigo]);
        $existeQr = (bool)$checkQrStmt->fetchColumn();
    } while ($existeQr);

    return $qrCodigo;
}

function formatearFechaEvento(?string $fecha): ?string
{
    if ($fecha === null || trim($fecha) === '') {
        return null;
    }

    try {
        $dt = new DateTime($fecha);
        return $dt->format('d/m/Y');
    } catch (Throwable $e) {
        return null;
    }
}

function obtenerTextoTrago(bool $incluyeTrago): string
{
    return $incluyeTrago ? 'INCLUYE TRAGO GRATIS' : '';
}

function mapAnticipadaRow(array $row): array
{
    $row['id'] = (int)$row['id'];
    $row['entrada_id'] = isset($row['entrada_id']) ? (int)$row['entrada_id'] : null;
    $row['evento_id'] = isset($row['evento_id']) ? (int)$row['evento_id'] : null;
    $row['promotor_id'] = isset($row['promotor_id']) ? (int)$row['promotor_id'] : null;
    $row['cantidad'] = (int)($row['cantidad'] ?? 1);
    $row['incluye_trago'] = filter_var($row['incluye_trago'], FILTER_VALIDATE_BOOLEAN);
    $row['entrada_precio'] = isset($row['entrada_precio']) ? (float)$row['entrada_precio'] : 0.0;

    return $row;
}

function obtenerUsuarioActivo(PDO $pdo, int $usuarioId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.nombre,
            u.telefono,
            u.rol_id,
            COALESCE(r.nombre, '') AS rol_nombre
        FROM usuarios u
        LEFT JOIN roles r
            ON r.id = u.rol_id
        WHERE u.id = :id
          AND u.activo = TRUE
        LIMIT 1
    ");
    $stmt->execute([':id' => $usuarioId]);

    $row = $stmt->fetch();

    return $row !== false ? $row : null;
}

function obtenerEntradaAnticipada(PDO $pdo): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            id,
            nombre,
            precio_base,
            activo
        FROM entradas
        WHERE LOWER(nombre) = LOWER('anticipada')
          AND activo = TRUE
        LIMIT 1
    ");
    $stmt->execute();

    $row = $stmt->fetch();

    return $row !== false ? $row : null;
}

function obtenerPromotoresActivos(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT
            u.id,
            u.nombre,
            u.telefono,
            COALESCE(r.nombre, '') AS rol_nombre
        FROM usuarios u
        INNER JOIN roles r
            ON r.id = u.rol_id
        WHERE u.activo = TRUE
          AND LOWER(r.nombre) IN ('promotor', 'promoter')
        ORDER BY u.nombre ASC
    ");

    return $stmt->fetchAll() ?: [];
}

function obtenerEventosActivos(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT
            e.id,
            e.nombre,
            e.fecha,
            e.capacidad
        FROM eventos e
        WHERE e.activo = TRUE
          AND NOT EXISTS (
              SELECT 1
              FROM cierres_eventos ce
              WHERE ce.evento_id = e.id
          )
        ORDER BY e.fecha ASC, e.id ASC
    ");

    return $stmt->fetchAll() ?: [];
}

try {
    $globalStart = microtime(true);

    $timer = microtime(true);
    $pdo = getPdo();
    logTime('PDO connect/reuse', $timer);

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $accion = strtolower(trim((string)($_GET['accion'] ?? $_GET['mode'] ?? 'lista')));

        if (in_array($accion, ['opciones', 'options'], true)) {
            $timer = microtime(true);
            $entradaAnticipada = obtenerEntradaAnticipada($pdo);
            logTime('GET opciones entrada anticipada', $timer);

            $timer = microtime(true);
            $eventos = obtenerEventosActivos($pdo);
            logTime('GET opciones eventos', $timer);

            $timer = microtime(true);
            $promotores = obtenerPromotoresActivos($pdo);
            logTime('GET opciones promotores', $timer);

            logTime('TOTAL anticipadas.php', $globalStart);

            jsonResponse(200, [
                'success' => true,
                'entrada_anticipada' => $entradaAnticipada,
                'eventos' => $eventos,
                'promotores' => $promotores,
            ]);
        }

        $timer = microtime(true);
        $stmt = $pdo->query("
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
            LEFT JOIN entradas e
                ON e.id = v.entrada_id
            LEFT JOIN eventos ev
                ON ev.id = v.evento_id
            WHERE v.estado = 'comprada'
              AND v.qr_generado_at IS NULL
              AND LOWER(COALESCE(e.nombre, '')) = LOWER('anticipada')
            ORDER BY v.fecha_venta ASC, v.id ASC
        ");
        $anticipadas = $stmt->fetchAll() ?: [];
        logTime('GET anticipadas', $timer);

        $timer = microtime(true);
        foreach ($anticipadas as &$row) {
            $row = mapAnticipadaRow($row);
        }
        unset($row);
        logTime('GET anticipadas armado PHP', $timer);

        logTime('TOTAL anticipadas.php', $globalStart);
        jsonResponse(200, $anticipadas);
    }

    if ($method !== 'POST') {
        jsonResponse(405, ['error' => 'Método no permitido.']);
    }

    $input = getJsonInput();
    $accion = strtolower(trim((string)($input['accion'] ?? 'crear')));

    if ($accion === 'crear') {
        $nombre = trim((string)($input['nombre'] ?? ''));
        $eventoId = ($input['evento_id'] ?? null) === '' || ($input['evento_id'] ?? null) === null
            ? null
            : (int)$input['evento_id'];
        $dni = trim((string)($input['dni'] ?? ''));
        $cantidad = isset($input['cantidad']) ? max(1, (int)$input['cantidad']) : 1;
        $incluyeTrago = normalizeBool($input['incluye_trago'] ?? false);
        $promotorId = isset($input['promotor_id']) && $input['promotor_id'] !== ''
            ? (int)$input['promotor_id']
            : null;
        $usuarioId = isset($input['usuario_id']) && $input['usuario_id'] !== ''
            ? (int)$input['usuario_id']
            : null;

        if ($nombre === '') {
            jsonResponse(400, ['error' => 'Debe indicar un nombre.']);
        }

        if ($usuarioId === null || $usuarioId <= 0) {
            jsonResponse(400, ['error' => 'Debe indicar el usuario que realizó la venta.']);
        }

        if ($promotorId === null || $promotorId <= 0) {
            jsonResponse(400, ['error' => 'Debe indicar un vendedor para registrar la venta.']);
        }

        if ($eventoId === null || $eventoId <= 0) {
            jsonResponse(400, ['error' => 'Debe indicar un evento para validar el cupo.']);
        }

        $timer = microtime(true);
        $usuarioExiste = obtenerUsuarioActivo($pdo, $usuarioId);
        logTime('crear obtener usuario vendedor', $timer);

        if ($usuarioExiste === null) {
            jsonResponse(400, ['error' => 'El usuario vendedor indicado no existe o está inactivo.']);
        }

        $timer = microtime(true);
        $vendedorData = obtenerUsuarioActivo($pdo, $promotorId);
        logTime('crear obtener promotor', $timer);

        if ($vendedorData === null) {
            jsonResponse(400, ['error' => 'El vendedor seleccionado no existe o está inactivo.']);
        }

        $vendedorRol = strtolower(trim((string)($vendedorData['rol_nombre'] ?? '')));
        $vendedorEsPromotor = in_array($vendedorRol, ['promotor', 'promoter'], true);

        $timer = microtime(true);
        $entradaData = obtenerEntradaAnticipada($pdo);
        logTime('crear obtener entrada anticipada', $timer);

        if ($entradaData === null) {
            jsonResponse(500, ['error' => 'No existe una entrada activa llamada Anticipada.']);
        }

        $entradaId = (int)$entradaData['id'];
        $precioUnitario = isset($entradaData['precio_base']) ? (float)$entradaData['precio_base'] : 0.0;

        $pdo->beginTransaction();

        try {
            if ($vendedorEsPromotor) {
                $timer = microtime(true);
                $cupoStmt = $pdo->prepare("
                    SELECT
                        id,
                        cupo_total,
                        cupo_vendido
                    FROM promotores_cupos
                    WHERE usuario_id = :usuario_id
                      AND evento_id = :evento_id
                      AND entrada_id = :entrada_id
                    ORDER BY id ASC
                    FOR UPDATE
                    LIMIT 1
                ");
                $cupoStmt->execute([
                    ':usuario_id' => $promotorId,
                    ':evento_id' => $eventoId,
                    ':entrada_id' => $entradaId,
                ]);
                $cupo = $cupoStmt->fetch();
                logTime('crear buscar cupo promotor', $timer);

                if ($cupo === false) {
                    $timer = microtime(true);
                    $insertCupoStmt = $pdo->prepare("
                        INSERT INTO promotores_cupos (
                            usuario_id,
                            evento_id,
                            entrada_id
                        )
                        VALUES (
                            :usuario_id,
                            :evento_id,
                            :entrada_id
                        )
                        RETURNING id, cupo_total, cupo_vendido
                    ");
                    $insertCupoStmt->execute([
                        ':usuario_id' => $promotorId,
                        ':evento_id' => $eventoId,
                        ':entrada_id' => $entradaId,
                    ]);
                    $cupo = $insertCupoStmt->fetch();
                    logTime('crear insertar cupo promotor', $timer);
                }

                $cupoTotal = isset($cupo['cupo_total']) ? (int)$cupo['cupo_total'] : 0;
                $cupoVendido = isset($cupo['cupo_vendido']) ? (int)$cupo['cupo_vendido'] : 0;
                $cupoDisponible = $cupoTotal - $cupoVendido;

                if ($cantidad > $cupoDisponible) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    jsonResponse(409, [
                        'error' => 'No hay cupo suficiente para esta venta.',
                        'cupo_disponible' => $cupoDisponible,
                    ]);
                }

                $timer = microtime(true);
                $updateCupoStmt = $pdo->prepare("
                    UPDATE promotores_cupos
                    SET cupo_vendido = cupo_vendido + :cantidad
                    WHERE id = :id
                ");
                $updateCupoStmt->execute([
                    ':cantidad' => $cantidad,
                    ':id' => $cupo['id'],
                ]);
                logTime('crear actualizar cupo promotor', $timer);
            }

            $timer = microtime(true);
            $insertVentaStmt = $pdo->prepare("
                INSERT INTO ventas_entradas (
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
                VALUES (
                    :entrada_id,
                    :cantidad,
                    :precio_unitario,
                    :evento_id,
                    :incluye_trago,
                    :usuario_id,
                    'comprada',
                    :nombre,
                    :dni,
                    :promotor_id
                )
                RETURNING id
            ");
            $insertVentaStmt->bindValue(':entrada_id', $entradaId, PDO::PARAM_INT);
            $insertVentaStmt->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
            $insertVentaStmt->bindValue(':precio_unitario', $precioUnitario);
            $insertVentaStmt->bindValue(':evento_id', $eventoId, PDO::PARAM_INT);
            $insertVentaStmt->bindValue(':incluye_trago', $incluyeTrago, PDO::PARAM_BOOL);
            $insertVentaStmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
            $insertVentaStmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            $insertVentaStmt->bindValue(':dni', $dni !== '' ? $dni : null, $dni !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $insertVentaStmt->bindValue(':promotor_id', $promotorId, PDO::PARAM_INT);
            $insertVentaStmt->execute();
            $nuevoId = (int)$insertVentaStmt->fetchColumn();
            logTime('crear insert venta anticipada', $timer);

            $timer = microtime(true);
            $detalleStmt = $pdo->prepare("
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
                LEFT JOIN entradas e
                    ON e.id = v.entrada_id
                LEFT JOIN eventos ev
                    ON ev.id = v.evento_id
                WHERE v.id = :id
                LIMIT 1
            ");
            $detalleStmt->execute([':id' => $nuevoId]);
            $nuevaAnticipada = $detalleStmt->fetch();
            logTime('crear fetch venta anticipada', $timer);

            if ($nuevaAnticipada !== false) {
                $nuevaAnticipada = mapAnticipadaRow($nuevaAnticipada);
            } else {
                $nuevaAnticipada = null;
            }

            $pdo->commit();

            logTime('TOTAL anticipadas.php', $globalStart);
            jsonResponse(200, [
                'success' => true,
                'mensaje' => 'Anticipada registrada correctamente.',
                'anticipada' => $nuevaAnticipada,
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    if ($accion === 'imprimir') {
        $id = isset($input['id']) ? (int)$input['id'] : 0;

        if ($id <= 0) {
            jsonResponse(400, ['error' => 'Debe indicar el ID de la anticipada.']);
        }

        $timer = microtime(true);
        $stmt = $pdo->prepare("
            SELECT
                v.id,
                v.nombre,
                v.dni,
                v.entrada_id,
                v.evento_id,
                v.usuario_id,
                v.promotor_id,
                v.cantidad,
                v.incluye_trago,
                v.qr_codigo,
                v.precio_unitario AS entrada_precio,
                e.nombre AS entrada_nombre,
                ev.nombre AS evento_nombre,
                ev.fecha AS evento_fecha
            FROM ventas_entradas v
            LEFT JOIN entradas e
                ON e.id = v.entrada_id
            LEFT JOIN eventos ev
                ON ev.id = v.evento_id
            WHERE v.id = :id
              AND v.estado = 'comprada'
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $anticipada = $stmt->fetch();
        logTime('imprimir obtener anticipada', $timer);

        if ($anticipada === false) {
            jsonResponse(404, ['error' => 'No se encontró la entrada anticipada.']);
        }

        $nombreEntrada = trim((string)($anticipada['entrada_nombre'] ?? 'Anticipada'));
        $precio = isset($anticipada['entrada_precio']) ? (float)$anticipada['entrada_precio'] : 0.0;
        $cantidad = max(1, (int)($anticipada['cantidad'] ?? 1));
        $incluyeTrago = filter_var($anticipada['incluye_trago'], FILTER_VALIDATE_BOOLEAN);
        $eventoId = isset($anticipada['evento_id']) ? (int)$anticipada['evento_id'] : null;
        $entradaId = isset($anticipada['entrada_id']) ? (int)$anticipada['entrada_id'] : null;
        $usuarioId = isset($anticipada['usuario_id']) ? (int)$anticipada['usuario_id'] : null;
        $eventoFecha = formatearFechaEvento($anticipada['evento_fecha'] ?? null);

        $timer = microtime(true);
        $printJobs = [];
        $ticketIds = [];
        $qrCodigos = [];

        $pdo->beginTransaction();
        try {
            $insertCopiaStmt = $pdo->prepare("
                INSERT INTO ventas_entradas (
                    entrada_id,
                    cantidad,
                    precio_unitario,
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
                RETURNING id
            ");
            $updateOriginalStmt = $pdo->prepare("
                UPDATE ventas_entradas
                SET
                    cantidad = 1,
                    qr_codigo = :qr_codigo,
                    qr_hash = :qr_hash,
                    qr_generado_at = NOW()
                WHERE id = :id
            ");

            for ($i = 0; $i < $cantidad; $i++) {
                $qrCodigo = generarQrUnico($pdo);
                $qrHash = generarQrHash($qrCodigo);

                if ($i === 0) {
                    $updateOriginalStmt->execute([
                        ':id' => $id,
                        ':qr_codigo' => $qrCodigo,
                        ':qr_hash' => $qrHash,
                    ]);
                    $ticketIds[] = (int)$anticipada['id'];
                    $qrCodigos[] = $qrCodigo;
                    continue;
                }

                $insertCopiaStmt->execute([
                    ':entrada_id' => $entradaId,
                    ':precio_unitario' => $precio,
                    ':evento_id' => $eventoId,
                    ':incluye_trago' => $incluyeTrago,
                    ':usuario_id' => $usuarioId,
                    ':nombre' => (string)($anticipada['nombre'] ?? ''),
                    ':dni' => ($anticipada['dni'] ?? null) !== null && trim((string)$anticipada['dni']) !== ''
                        ? (string)$anticipada['dni']
                        : null,
                    ':promotor_id' => ($anticipada['promotor_id'] ?? null) !== null
                        ? (int)$anticipada['promotor_id']
                        : null,
                    ':qr_codigo' => $qrCodigo,
                    ':qr_hash' => $qrHash,
                ]);
                $ticketIds[] = (int)$insertCopiaStmt->fetchColumn();
                $qrCodigos[] = $qrCodigo;
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        logTime('imprimir generar qr por entrada', $timer);

        for ($i = 0; $i < $cantidad; $i++) {
            $printJobs[] = [
                'ticket_id' => $ticketIds[$i] ?? (int)$anticipada['id'],
                'evento_id' => $eventoId,
                'entrada_id' => $entradaId,
                'usuario_id' => $usuarioId,
                'tipo' => $nombreEntrada,
                'precio' => (int)round($precio),
                'precio_formateado' => number_format($precio, 0, ',', '.'),
                'incluye_trago' => $incluyeTrago,
                'trago_texto' => obtenerTextoTrago($incluyeTrago),
                'qr' => $qrCodigos[$i] ?? '',
                'estado' => 'comprada',
                'fecha' => date('d/m/Y'),
                'hora' => date('H:i'),
                'negocio' => 'SANTAS',
                'ancho_papel' => '80mm',
                'evento_fecha' => $eventoFecha,
                'es_cortesia' => false,
                'lista' => '',
            ];
        }
        logTime('imprimir armar print_jobs', $timer);

        logTime('TOTAL anticipadas.php', $globalStart);
        jsonResponse(200, [
            'success' => true,
            'mensaje' => 'Ticket preparado para impresión.',
            'id_eliminado' => $id,
            'entrada' => $nombreEntrada,
            'print_jobs' => $printJobs,
        ]);
    }

    jsonResponse(400, ['error' => 'Acción no soportada.']);
} catch (Throwable $e) {
    error_log('anticipadas.php ERROR: ' . $e->getMessage());

    jsonResponse(500, [
        'error' => 'Error interno del servidor',
        'detalle' => $e->getMessage(),
    ]);
}
