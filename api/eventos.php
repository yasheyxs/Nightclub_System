<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
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

function jsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function getJsonInput(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

function mapEvent(array $event): array
{
    return [
        'id' => (int)$event['id'],
        'nombre' => (string)$event['nombre'],
        'detalle' => $event['detalle'],
        'fecha' => (string)$event['fecha'],
        'capacidad' => (int)$event['capacidad'],
        'activo' => filter_var($event['activo'], FILTER_VALIDATE_BOOLEAN),
    ];
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

function obtenerEventoPorId(PDO $pdo, int $eventoId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            id,
            nombre,
            detalle,
            fecha,
            capacidad,
            activo
        FROM eventos
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $eventoId]);

    $row = $stmt->fetch();

    return $row !== false ? $row : null;
}

function desactivarEventosPasados(PDO $pdo): void
{
    $pdo->exec("
        UPDATE eventos
        SET activo = FALSE
        WHERE fecha < NOW()
          AND activo = TRUE
    ");
}

function buscarEventoActivoDelDia(PDO $pdo, string $fechaDia): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            e.id,
            e.nombre,
            e.detalle,
            e.fecha,
            e.capacidad,
            e.activo
        FROM eventos e
        WHERE CAST(e.fecha AS date) = :fecha
          AND e.activo = TRUE
          AND NOT EXISTS (
              SELECT 1
              FROM cierres_eventos ce
              WHERE ce.evento_id = e.id
          )
        ORDER BY e.fecha ASC, e.id ASC
        LIMIT 1
    ");
    $stmt->execute([':fecha' => $fechaDia]);

    $row = $stmt->fetch();

    return $row !== false ? $row : null;
}

function buscarEventoPorFechaExacta(PDO $pdo, string $fechaExacta): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            id,
            nombre,
            detalle,
            fecha,
            capacidad,
            activo
        FROM eventos
        WHERE fecha = :fecha
        LIMIT 1
    ");
    $stmt->execute([':fecha' => $fechaExacta]);

    $row = $stmt->fetch();

    return $row !== false ? $row : null;
}

function generarSabados(PDO $pdo, int $cantidad = 5, int $cupo = 1000): array
{
    $start = microtime(true);

    // ================================
    // 1. CALCULAR PRÓXIMO SÁBADO
    // ================================
    $hoy = new DateTime('today');
    $weekday = (int)$hoy->format('w');
    $diasHastaSabado = (6 - $weekday + 7) % 7;
    $hoy->modify("+{$diasHastaSabado} day");

    $fechas = [];
    for ($i = 0; $i < $cantidad; $i++) {
        $fechas[] = $hoy->format('Y-m-d 23:00:00');
        $hoy->modify('+7 day');
    }

    // ================================
    // 2. INSERT MASIVO (SIN DUPLICAR)
    // ================================
    $values = [];
    $params = [];

    foreach ($fechas as $i => $fecha) {
        $values[] = "(:nombre{$i}, NULL, :fecha{$i}, :capacidad{$i}, TRUE, NOW())";
        $params[":nombre{$i}"] = 'Evento - ' . date('d/m/Y', strtotime($fecha));
        $params[":fecha{$i}"] = $fecha;
        $params[":capacidad{$i}"] = $cupo;
    }

    $sqlInsert = "
        INSERT INTO eventos (nombre, detalle, fecha, capacidad, activo, fecha_creacion)
        VALUES " . implode(',', $values) . "
        ON CONFLICT (fecha) DO NOTHING
    ";

    $timer = microtime(true);
    $stmt = $pdo->prepare($sqlInsert);
    $stmt->execute($params);
    logTime('generar sabados insert masivo', $timer);

    // ================================
    // 3. TRAER TODOS DE UNA SOLA VEZ
    // ================================
    $placeholders = implode(',', array_fill(0, count($fechas), '?'));

    $timer = microtime(true);
    $stmt = $pdo->prepare("
        SELECT
            e.id,
            e.nombre,
            e.detalle,
            e.fecha,
            e.capacidad,
            e.activo
        FROM eventos e
        WHERE e.fecha IN ($placeholders)
          AND e.activo = TRUE
          AND NOT EXISTS (
              SELECT 1
              FROM cierres_eventos ce
              WHERE ce.evento_id = e.id
          )
        ORDER BY e.fecha ASC
    ");
    $stmt->execute($fechas);
    $eventos = $stmt->fetchAll() ?: [];
    logTime('generar sabados select masivo', $timer);

    logTime('TOTAL generar sabados OPTIMIZADO', $start);

    return $eventos;
}

try {
    $globalStart = microtime(true);

    $timer = microtime(true);
    $pdo = getPdo();
    logTime('PDO connect/reuse', $timer);

    $timer = microtime(true);
    desactivarEventosPasados($pdo);
    logTime('desactivar eventos pasados', $timer);

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $upcoming = isset($_GET['upcoming']) && $_GET['upcoming'] === '1';
        $calendar = isset($_GET['calendar']) && $_GET['calendar'] === '1';

        if ($upcoming) {
            $timer = microtime(true);
            $eventos = generarSabados($pdo, 5, 1000);
            logTime('GET upcoming generar sabados', $timer);

            $timer = microtime(true);
            $response = array_map('mapEvent', $eventos);
            logTime('GET upcoming armado PHP', $timer);

            logTime('TOTAL eventos.php', $globalStart);
            jsonResponse(200, $response);
        }

        if ($calendar) {
            $timer = microtime(true);
            generarSabados($pdo, 5, 1000);
            logTime('GET calendar generar sabados', $timer);

            $desde = (new DateTime('today'))->format('Y-m-d');
            $hasta = (new DateTime('+60 days'))->format('Y-m-d');

            $timer = microtime(true);
            $stmt = $pdo->prepare("
                SELECT
                    e.id,
                    e.nombre,
                    e.detalle,
                    e.fecha,
                    e.capacidad,
                    e.activo
                FROM eventos e
                WHERE CAST(e.fecha AS date) BETWEEN :desde AND :hasta
                  AND e.activo = TRUE
                  AND NOT EXISTS (
                      SELECT 1
                      FROM cierres_eventos ce
                      WHERE ce.evento_id = e.id
                  )
                ORDER BY e.fecha ASC, e.id ASC
            ");
            $stmt->execute([
                ':desde' => $desde,
                ':hasta' => $hasta,
            ]);
            $rows = $stmt->fetchAll() ?: [];
            logTime('GET calendar query', $timer);

            $timer = microtime(true);
            $response = array_map('mapEvent', $rows);
            logTime('GET calendar armado PHP', $timer);

            logTime('TOTAL eventos.php', $globalStart);
            jsonResponse(200, $response);
        }

        $timer = microtime(true);
        $stmt = $pdo->query("
            SELECT
                e.id,
                e.nombre,
                e.detalle,
                e.fecha,
                e.capacidad,
                e.activo
            FROM eventos e
            WHERE e.activo = TRUE
              AND e.fecha >= NOW()
              AND NOT EXISTS (
                  SELECT 1
                  FROM cierres_eventos ce
                  WHERE ce.evento_id = e.id
              )
            ORDER BY e.fecha ASC, e.id ASC
        ");
        $rows = $stmt->fetchAll() ?: [];
        logTime('GET listado general query', $timer);

        $timer = microtime(true);
        $response = array_map('mapEvent', $rows);
        logTime('GET listado general armado PHP', $timer);

        logTime('TOTAL eventos.php', $globalStart);
        jsonResponse(200, $response);
    }

    if ($method === 'POST') {
        $input = getJsonInput();

        if (
            !isset($input['nombre']) ||
            !isset($input['fecha']) ||
            !isset($input['capacidad'])
        ) {
            jsonResponse(400, [
                'error' => 'Campos obligatorios: nombre, fecha, capacidad',
            ]);
        }

        $nombre = trim((string)$input['nombre']);
        $fecha = trim((string)$input['fecha']);
        $capacidad = (int)$input['capacidad'];
        $detalle = array_key_exists('detalle', $input) ? $input['detalle'] : null;

        if ($nombre === '' || $fecha === '' || $capacidad <= 0) {
            jsonResponse(400, [
                'error' => 'Datos inválidos',
            ]);
        }

        $timer = microtime(true);
        $stmt = $pdo->prepare("
            INSERT INTO eventos (
                nombre,
                detalle,
                fecha,
                capacidad,
                activo,
                fecha_creacion
            )
            VALUES (
                :nombre,
                :detalle,
                :fecha,
                :capacidad,
                TRUE,
                NOW()
            )
            RETURNING id, nombre, detalle, fecha, capacidad, activo
        ");
        $stmt->execute([
            ':nombre' => $nombre,
            ':detalle' => $detalle,
            ':fecha' => $fecha,
            ':capacidad' => $capacidad,
        ]);
        $evento = $stmt->fetch();
        logTime('POST crear evento', $timer);

        if ($evento === false) {
            jsonResponse(500, [
                'error' => 'No se pudo crear el evento',
            ]);
        }

        logTime('TOTAL eventos.php', $globalStart);
        jsonResponse(200, mapEvent($evento));
    }

    if ($method === 'PUT') {
        if (!isset($_GET['id'])) {
            jsonResponse(400, [
                'error' => 'Debe especificar el ID en la URL',
            ]);
        }

        $id = (int)$_GET['id'];
        if ($id <= 0) {
            jsonResponse(400, [
                'error' => 'ID inválido',
            ]);
        }

        $timer = microtime(true);
        $evento = obtenerEventoPorId($pdo, $id);
        logTime('PUT obtener evento', $timer);

        if ($evento === null) {
            jsonResponse(404, [
                'error' => 'Evento no encontrado',
            ]);
        }

        $timer = microtime(true);
        $cerrado = eventoEstaCerrado($pdo, $id);
        logTime('PUT validar evento cerrado', $timer);

        if ($cerrado) {
            jsonResponse(409, [
                'error' => 'El evento está cerrado y no puede editarse.',
            ]);
        }

        $input = getJsonInput();

        $nombre = array_key_exists('nombre', $input)
            ? trim((string)$input['nombre'])
            : (string)$evento['nombre'];

        $detalle = array_key_exists('detalle', $input)
            ? $input['detalle']
            : $evento['detalle'];

        $capacidad = array_key_exists('capacidad', $input)
            ? (int)$input['capacidad']
            : (int)$evento['capacidad'];

        if ($nombre === '' || $capacidad <= 0) {
            jsonResponse(400, [
                'error' => 'Datos inválidos',
            ]);
        }

        $timer = microtime(true);
        $stmt = $pdo->prepare("
            UPDATE eventos
            SET
                nombre = :nombre,
                detalle = :detalle,
                capacidad = :capacidad
            WHERE id = :id
            RETURNING id, nombre, detalle, fecha, capacidad, activo
        ");
        $stmt->execute([
            ':id' => $id,
            ':nombre' => $nombre,
            ':detalle' => $detalle,
            ':capacidad' => $capacidad,
        ]);
        $updated = $stmt->fetch();
        logTime('PUT actualizar evento', $timer);

        if ($updated === false) {
            jsonResponse(500, [
                'error' => 'No se pudo actualizar el evento',
            ]);
        }

        logTime('TOTAL eventos.php', $globalStart);
        jsonResponse(200, mapEvent($updated));
    }

    if ($method === 'DELETE') {
        if (!isset($_GET['id'])) {
            jsonResponse(400, [
                'error' => 'Debe especificar el ID',
            ]);
        }

        $id = (int)$_GET['id'];
        if ($id <= 0) {
            jsonResponse(400, [
                'error' => 'ID inválido',
            ]);
        }

        $timer = microtime(true);
        $evento = obtenerEventoPorId($pdo, $id);
        logTime('DELETE obtener evento', $timer);

        if ($evento === null) {
            jsonResponse(404, [
                'error' => 'Evento no encontrado',
            ]);
        }

        $timer = microtime(true);
        $cerrado = eventoEstaCerrado($pdo, $id);
        logTime('DELETE validar evento cerrado', $timer);

        if ($cerrado) {
            jsonResponse(409, [
                'error' => 'El evento está cerrado y no puede modificarse.',
            ]);
        }

        $timer = microtime(true);
        $stmt = $pdo->prepare("
            UPDATE eventos
            SET activo = FALSE
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        logTime('DELETE soft delete evento', $timer);

        logTime('TOTAL eventos.php', $globalStart);
        jsonResponse(200, ['success' => true]);
    }

    jsonResponse(405, ['error' => 'Método no permitido']);
} catch (Throwable $e) {
    error_log('eventos.php ERROR: ' . $e->getMessage());

    jsonResponse(500, [
        'error' => 'Error interno del servidor',
        'detalle' => $e->getMessage(),
    ]);
}
