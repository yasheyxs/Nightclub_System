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
    $row['incluye_trago'] = filter_var($row['incluye_trago'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $row['entrada_precio'] = isset($row['entrada_precio']) ? (float)$row['entrada_precio'] : 0.0;
    $row['qr_codigo'] = isset($row['qr_codigo']) ? (string)$row['qr_codigo'] : null;
    $row['qr_generado_at'] = $row['qr_generado_at'] ?? null;

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

function construirPrintJobDesdeVenta(array $venta): array
{
    $precio = isset($venta['entrada_precio']) ? (float)$venta['entrada_precio'] : 0.0;
    $incluyeTrago = filter_var($venta['incluye_trago'] ?? false, FILTER_VALIDATE_BOOLEAN);

    return [
        'ticket_id' => isset($venta['id']) ? (int)$venta['id'] : 0,
        'evento_id' => isset($venta['evento_id']) ? (int)$venta['evento_id'] : null,
        'entrada_id' => isset($venta['entrada_id']) ? (int)$venta['entrada_id'] : null,
        'usuario_id' => isset($venta['usuario_id']) ? (int)$venta['usuario_id'] : null,
        'tipo' => trim((string)($venta['entrada_nombre'] ?? 'Anticipada')),
        'precio' => (int)round($precio),
        'precio_formateado' => number_format($precio, 0, ',', '.'),
        'incluye_trago' => $incluyeTrago,
        'trago_texto' => obtenerTextoTrago($incluyeTrago),
        'qr' => (string)($venta['qr_codigo'] ?? ''),
        'estado' => (string)($venta['estado'] ?? 'comprada'),
        'fecha' => date('d/m/Y'),
        'hora' => date('H:i'),
        'negocio' => 'SANTAS',
        'ancho_papel' => '80mm',
        'evento_fecha' => formatearFechaEvento($venta['evento_fecha'] ?? null),
        'es_cortesia' => false,
        'lista' => '',
        'nombre' => (string)($venta['nombre'] ?? ''),
        'dni' => (string)($venta['dni'] ?? ''),
    ];
}

function obtenerVentaAnticipadaPorId(PDO $pdo, int $id): ?array
{
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
            v.qr_hash,
            v.qr_generado_at,
            v.estado,
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
          AND LOWER(COALESCE(e.nombre, '')) = LOWER('anticipada')
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);

    $row = $stmt->fetch();

    return $row !== false ? $row : null;
}

function obtenerCantidadEscaneadasPorEvento(PDO $pdo, ?int $eventoId): int
{
    if ($eventoId === null || $eventoId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)::int
        FROM ventas_entradas
        WHERE evento_id = :evento_id
          AND estado = 'usada'
    ");
    $stmt->execute([':evento_id' => $eventoId]);

    return (int)$stmt->fetchColumn();
}

function marcarAnticipadasComoImpresas(PDO $pdo, array $ids): int
{
    $ids = array_values(array_unique(array_filter(
        array_map(static fn($id) => (int)$id, $ids),
        static fn($id) => $id > 0
    )));

    if ($ids === []) {
        return 0;
    }

    $placeholders = implode(', ', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("
        UPDATE ventas_entradas
        SET estado = 'impresa'
        WHERE id IN ($placeholders)
          AND estado = 'comprada'
    ");

    $stmt->execute($ids);

    return $stmt->rowCount();
}

function materializarAnticipadas(PDO $pdo, int $id): array
{
    $pdo->beginTransaction();

    try {
        $lockStmt = $pdo->prepare("
            SELECT
                id,
                cantidad,
                qr_codigo,
                qr_hash,
                qr_generado_at,
                estado
            FROM ventas_entradas
            WHERE id = :id
              AND estado = 'comprada'
            FOR UPDATE
        ");
        $lockStmt->execute([':id' => $id]);
        $base = $lockStmt->fetch();

        if ($base === false) {
            throw new RuntimeException('No se encontró la anticipada.');
        }

        $venta = obtenerVentaAnticipadaPorId($pdo, $id);

        if ($venta === null) {
            throw new RuntimeException('No se encontró la anticipada.');
        }

        if (strtolower((string)($venta['estado'] ?? '')) !== 'comprada') {
            throw new RuntimeException('La anticipada no está disponible para procesar.');
        }

        $cantidad = max(1, (int)($venta['cantidad'] ?? 1));
        $tickets = [];

        if ($cantidad === 1) {
            if (empty($venta['qr_codigo'])) {
                $qrCodigo = generarQrUnico($pdo);
                $qrHash = generarQrHash($qrCodigo);

                $updateStmt = $pdo->prepare("
                    UPDATE ventas_entradas
                    SET
                        qr_codigo = :qr_codigo,
                        qr_hash = :qr_hash,
                        qr_generado_at = COALESCE(qr_generado_at, NOW())
                    WHERE id = :id
                ");
                $updateStmt->execute([
                    ':id' => $id,
                    ':qr_codigo' => $qrCodigo,
                    ':qr_hash' => $qrHash,
                ]);

                $venta['qr_codigo'] = $qrCodigo;
                $venta['qr_hash'] = $qrHash;
                $venta['qr_generado_at'] = date('Y-m-d H:i:s');
            }

            $tickets[] = $venta;
            $pdo->commit();

            return $tickets;
        }

        $updateOriginalStmt = $pdo->prepare("
            UPDATE ventas_entradas
            SET
                cantidad = 1,
                qr_codigo = :qr_codigo,
                qr_hash = :qr_hash,
                qr_generado_at = COALESCE(qr_generado_at, NOW())
            WHERE id = :id
        ");

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

        for ($i = 0; $i < $cantidad; $i++) {
            $qrCodigo = generarQrUnico($pdo);
            $qrHash = generarQrHash($qrCodigo);

            if ($i === 0) {
                $updateOriginalStmt->execute([
                    ':id' => $id,
                    ':qr_codigo' => $qrCodigo,
                    ':qr_hash' => $qrHash,
                ]);

                $venta['cantidad'] = 1;
                $venta['qr_codigo'] = $qrCodigo;
                $venta['qr_hash'] = $qrHash;
                $venta['qr_generado_at'] = date('Y-m-d H:i:s');
                $tickets[] = $venta;
                continue;
            }

            $insertCopiaStmt->bindValue(':entrada_id', (int)$venta['entrada_id'], PDO::PARAM_INT);
            $insertCopiaStmt->bindValue(':precio_unitario', (float)$venta['entrada_precio']);
            $insertCopiaStmt->bindValue(
                ':evento_id',
                $venta['evento_id'] !== null ? (int)$venta['evento_id'] : null,
                $venta['evento_id'] !== null ? PDO::PARAM_INT : PDO::PARAM_NULL
            );
            $insertCopiaStmt->bindValue(
                ':incluye_trago',
                filter_var($venta['incluye_trago'], FILTER_VALIDATE_BOOLEAN),
                PDO::PARAM_BOOL
            );
            $insertCopiaStmt->bindValue(
                ':usuario_id',
                $venta['usuario_id'] !== null ? (int)$venta['usuario_id'] : null,
                $venta['usuario_id'] !== null ? PDO::PARAM_INT : PDO::PARAM_NULL
            );
            $insertCopiaStmt->bindValue(':nombre', (string)$venta['nombre'], PDO::PARAM_STR);
            $insertCopiaStmt->bindValue(
                ':dni',
                (($venta['dni'] ?? null) !== null && trim((string)$venta['dni']) !== '') ? (string)$venta['dni'] : null,
                (($venta['dni'] ?? null) !== null && trim((string)$venta['dni']) !== '') ? PDO::PARAM_STR : PDO::PARAM_NULL
            );
            $insertCopiaStmt->bindValue(
                ':promotor_id',
                $venta['promotor_id'] !== null ? (int)$venta['promotor_id'] : null,
                $venta['promotor_id'] !== null ? PDO::PARAM_INT : PDO::PARAM_NULL
            );
            $insertCopiaStmt->bindValue(':qr_codigo', $qrCodigo, PDO::PARAM_STR);
            $insertCopiaStmt->bindValue(':qr_hash', $qrHash, PDO::PARAM_STR);
            $insertCopiaStmt->execute();

            $nuevoId = (int)$insertCopiaStmt->fetchColumn();

            $tickets[] = [
                'id' => $nuevoId,
                'nombre' => $venta['nombre'],
                'dni' => $venta['dni'],
                'entrada_id' => $venta['entrada_id'],
                'evento_id' => $venta['evento_id'],
                'usuario_id' => $venta['usuario_id'],
                'promotor_id' => $venta['promotor_id'],
                'cantidad' => 1,
                'incluye_trago' => $venta['incluye_trago'],
                'qr_codigo' => $qrCodigo,
                'qr_hash' => $qrHash,
                'qr_generado_at' => date('Y-m-d H:i:s'),
                'estado' => 'comprada',
                'entrada_precio' => $venta['entrada_precio'],
                'entrada_nombre' => $venta['entrada_nombre'],
                'evento_nombre' => $venta['evento_nombre'],
                'evento_fecha' => $venta['evento_fecha'],
            ];
        }

        $pdo->commit();

        return $tickets;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
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
                v.qr_codigo,
                v.qr_generado_at,
                e.nombre AS entrada_nombre,
                v.precio_unitario AS entrada_precio,
                ev.nombre AS evento_nombre
            FROM ventas_entradas v
            LEFT JOIN entradas e
                ON e.id = v.entrada_id
            LEFT JOIN eventos ev
                ON ev.id = v.evento_id
            WHERE v.estado = 'comprada'
              AND LOWER(COALESCE(e.nombre, '')) = LOWER('anticipada')
            ORDER BY v.fecha_venta DESC, v.id DESC
        ");
        $expandido = [];

        foreach ($anticipadas as $row) {
            $cantidad = max(1, (int)$row['cantidad']);

            for ($i = 0; $i < $cantidad; $i++) {
                $row['cantidad'] = 1;
                $expandido[] = $row;
            }
        }

        $anticipadas = $expandido;
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
        $incluyeTrago = filter_var(
            $input['incluye_trago'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );

        $incluyeTrago = $incluyeTrago ?? false;
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
                    v.qr_codigo,
                    v.qr_generado_at,
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

    if ($accion === 'preparar' || $accion === 'imprimir' || $accion === 'descargar_qr') {
        $id = isset($input['id']) ? (int)$input['id'] : 0;

        if ($id <= 0) {
            jsonResponse(400, ['error' => 'Debe indicar el ID de la anticipada.']);
        }

        $timer = microtime(true);
        $tickets = materializarAnticipadas($pdo, $id);
        logTime('materializar anticipadas', $timer);

        $printJobs = array_map('construirPrintJobDesdeVenta', $tickets);

        logTime('TOTAL anticipadas.php', $globalStart);
        jsonResponse(200, [
            'success' => true,
            'mensaje' => 'Anticipada preparada correctamente.',
            'tickets' => $tickets,
            'print_jobs' => $printJobs,
        ]);
    }

    if ($accion === 'confirmar_impresion') {
        $ticketsIds = isset($input['tickets_ids']) && is_array($input['tickets_ids'])
            ? $input['tickets_ids']
            : [];

        $eventoId = isset($input['evento_id']) && $input['evento_id'] !== ''
            ? (int)$input['evento_id']
            : null;

        if ($ticketsIds === []) {
            jsonResponse(400, ['error' => 'Debe indicar los tickets impresos.']);
        }

        $pdo->beginTransaction();

        try {
            $timer = microtime(true);
            $actualizados = marcarAnticipadasComoImpresas($pdo, $ticketsIds);
            logTime('confirmar impresion update usadas', $timer);

            $pdo->commit();

            logTime('TOTAL anticipadas.php', $globalStart);
            jsonResponse(200, [
                'success' => true,
                'mensaje' => 'Anticipadas marcadas como impresas.',
                'actualizados' => $actualizados,
                'entradas_escaneadas' => obtenerCantidadEscaneadasPorEvento($pdo, $eventoId),
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    if ($accion === 'eliminar') {
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        $usuarioId = isset($input['usuario_id']) && $input['usuario_id'] !== ''
            ? (int)$input['usuario_id']
            : null;
        $motivo = trim((string)($input['motivo'] ?? 'Eliminación manual desde anticipadas'));

        if ($id <= 0) {
            jsonResponse(400, ['error' => 'Debe indicar el ID de la anticipada.']);
        }

        $timer = microtime(true);
        $stmt = $pdo->prepare("
            SELECT
                v.id,
                v.evento_id,
                v.entrada_id,
                v.promotor_id,
                v.estado,
                v.cantidad,
                e.nombre AS entrada_nombre
            FROM ventas_entradas v
            LEFT JOIN entradas e
                ON e.id = v.entrada_id
            WHERE v.id = :id
              AND LOWER(COALESCE(e.nombre, '')) = LOWER('anticipada')
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $venta = $stmt->fetch();
        logTime('eliminar obtener anticipada', $timer);

        if ($venta === false) {
            jsonResponse(404, ['error' => 'No se encontró la anticipada.']);
        }

        $estado = strtolower((string)($venta['estado'] ?? ''));

        if ($estado !== 'comprada') {
            jsonResponse(409, ['error' => 'Solo se pueden eliminar anticipadas en estado comprada.']);
        }

        $pdo->beginTransaction();

        try {
            $observacion = $usuarioId !== null && $usuarioId > 0
                ? $motivo . ' | user=' . $usuarioId
                : $motivo;

            $timer = microtime(true);
            $updateStmt = $pdo->prepare("
                UPDATE ventas_entradas
                SET
                    estado = 'anulada',
                    observaciones = CASE
                        WHEN COALESCE(observaciones, '') = ''
                            THEN :observacion
                        ELSE observaciones || ' | ' || :observacion
                    END
                WHERE id = :id
            ");
            $updateStmt->execute([
                ':id' => $id,
                ':observacion' => $observacion,
            ]);
            logTime('eliminar update anticipada', $timer);

            $promotorId = isset($venta['promotor_id']) ? (int)$venta['promotor_id'] : 0;
            $eventoId = isset($venta['evento_id']) ? (int)$venta['evento_id'] : 0;
            $entradaId = isset($venta['entrada_id']) ? (int)$venta['entrada_id'] : 0;
            $cantidad = max(1, (int)($venta['cantidad'] ?? 1));

            if ($promotorId > 0 && $eventoId > 0 && $entradaId > 0) {
                $timer = microtime(true);
                $cupoStmt = $pdo->prepare("
                    UPDATE promotores_cupos
                    SET cupo_vendido = GREATEST(cupo_vendido - :cantidad, 0)
                    WHERE usuario_id = :usuario_id
                      AND evento_id = :evento_id
                      AND entrada_id = :entrada_id
                ");
                $cupoStmt->execute([
                    ':cantidad' => $cantidad,
                    ':usuario_id' => $promotorId,
                    ':evento_id' => $eventoId,
                    ':entrada_id' => $entradaId,
                ]);
                logTime('eliminar update cupo promotor', $timer);
            }

            $pdo->commit();

            logTime('TOTAL anticipadas.php', $globalStart);
            jsonResponse(200, [
                'success' => true,
                'mensaje' => 'Anticipada eliminada correctamente.',
                'id_eliminado' => $id,
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    jsonResponse(400, ['error' => 'Acción no soportada.']);
} catch (Throwable $e) {
    error_log('anticipadas.php ERROR: ' . $e->getMessage());

    jsonResponse(500, [
        'error' => 'Error interno del servidor',
        'detalle' => $e->getMessage(),
    ]);
}
