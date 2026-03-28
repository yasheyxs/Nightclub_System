<?php

declare(strict_types=1);

// =========================
// CONFIG
// =========================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// =========================
// HELPERS
// =========================
function jsonResponse(int $statusCode, array $data): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getJsonInput(): array
{
    $raw = file_get_contents("php://input");
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
        jsonResponse(400, [
            "ok" => false,
            "message" => "El cuerpo enviado no es un JSON válido."
        ]);
    }

    return $decoded;
}

function toBoolOrNull(mixed $value): ?bool
{
    if ($value === null) {
        return null;
    }

    if (is_bool($value)) {
        return $value;
    }

    if ($value === 1 || $value === "1" || $value === "true" || $value === "TRUE") {
        return true;
    }

    if ($value === 0 || $value === "0" || $value === "false" || $value === "FALSE") {
        return false;
    }

    return null;
}

function isValidTimeOrNull(mixed $value): bool
{
    if ($value === null || $value === '') {
        return true;
    }

    return preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string)$value) === 1;
}

function sanitizeEntradaPayload(array $input, bool $isUpdate = false): array
{
    $nombre = array_key_exists('nombre', $input) ? trim((string)$input['nombre']) : null;
    $descripcion = array_key_exists('descripcion', $input) ? trim((string)$input['descripcion']) : null;
    $precioBase = array_key_exists('precio_base', $input) ? $input['precio_base'] : null;
    $cambioAutomatico = array_key_exists('cambio_automatico', $input) ? toBoolOrNull($input['cambio_automatico']) : null;
    $horaInicio = array_key_exists('hora_inicio_cambio', $input) ? $input['hora_inicio_cambio'] : null;
    $horaFin = array_key_exists('hora_fin_cambio', $input) ? $input['hora_fin_cambio'] : null;
    $nuevoPrecio = array_key_exists('nuevo_precio', $input) ? $input['nuevo_precio'] : null;
    $activo = array_key_exists('activo', $input) ? toBoolOrNull($input['activo']) : null;

    if (!$isUpdate) {
        if ($nombre === null || $nombre === '') {
            jsonResponse(400, [
                "ok" => false,
                "message" => "El campo 'nombre' es obligatorio."
            ]);
        }

        if ($precioBase === null || $precioBase === '') {
            jsonResponse(400, [
                "ok" => false,
                "message" => "El campo 'precio_base' es obligatorio."
            ]);
        }
    }

    if ($precioBase !== null && $precioBase !== '' && !is_numeric($precioBase)) {
        jsonResponse(400, [
            "ok" => false,
            "message" => "El campo 'precio_base' debe ser numérico."
        ]);
    }

    if ($nuevoPrecio !== null && $nuevoPrecio !== '' && !is_numeric($nuevoPrecio)) {
        jsonResponse(400, [
            "ok" => false,
            "message" => "El campo 'nuevo_precio' debe ser numérico."
        ]);
    }

    if (!isValidTimeOrNull($horaInicio)) {
        jsonResponse(400, [
            "ok" => false,
            "message" => "El campo 'hora_inicio_cambio' debe tener formato HH:MM o HH:MM:SS."
        ]);
    }

    if (!isValidTimeOrNull($horaFin)) {
        jsonResponse(400, [
            "ok" => false,
            "message" => "El campo 'hora_fin_cambio' debe tener formato HH:MM o HH:MM:SS."
        ]);
    }

    // Validación lógica del cambio automático
    $hayCambioAutomatico = ($cambioAutomatico === true);

    if ($hayCambioAutomatico) {
        if ($nuevoPrecio === null || $nuevoPrecio === '') {
            jsonResponse(400, [
                "ok" => false,
                "message" => "Si 'cambio_automatico' es true, 'nuevo_precio' es obligatorio."
            ]);
        }

        if (($horaInicio === null || $horaInicio === '') || ($horaFin === null || $horaFin === '')) {
            jsonResponse(400, [
                "ok" => false,
                "message" => "Si 'cambio_automatico' es true, 'hora_inicio_cambio' y 'hora_fin_cambio' son obligatorios."
            ]);
        }
    }

    return [
        "nombre" => $nombre,
        "descripcion" => $descripcion !== '' ? $descripcion : null,
        "precio_base" => $precioBase !== '' ? $precioBase : null,
        "cambio_automatico" => $cambioAutomatico,
        "hora_inicio_cambio" => $horaInicio !== '' ? $horaInicio : null,
        "hora_fin_cambio" => $horaFin !== '' ? $horaFin : null,
        "nuevo_precio" => $nuevoPrecio !== '' ? $nuevoPrecio : null,
        "activo" => $activo
    ];
}

function getConnection(): PDO
{
    $host = "aws-1-us-east-2.pooler.supabase.com";
    $port = "5432";
    $dbname = "postgres";
    $user = "postgres.kxvogvgsgwfvtmidabyp";
    $password = "lapicero30!";

    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password;sslmode=require"
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    return $pdo;
}

// =========================
// MAIN
// =========================
try {
    $conn = getConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    // =========================
    // GET
    // - /api/entradas.php           -> lista
    // - /api/entradas.php?id=5      -> detalle
    // =========================
    if ($method === 'GET') {
        if ($id !== null && $id > 0) {
            $stmt = $conn->prepare("
                SELECT
                    id,
                    nombre,
                    descripcion,
                    precio_base,
                    cambio_automatico,
                    nuevo_precio,
                    fecha_creacion,
                    activo,
                    hora_inicio_cambio,
                    hora_fin_cambio
                FROM entradas
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $entrada = $stmt->fetch();

            if (!$entrada) {
                jsonResponse(404, [
                    "ok" => false,
                    "message" => "Entrada no encontrada."
                ]);
            }

            jsonResponse(200, [
                "ok" => true,
                "data" => $entrada
            ]);
        }

        $stmt = $conn->query("
            SELECT
                id,
                nombre,
                descripcion,
                precio_base,
                cambio_automatico,
                nuevo_precio,
                fecha_creacion,
                activo,
                hora_inicio_cambio,
                hora_fin_cambio
            FROM entradas
            ORDER BY id ASC
        ");
        $entradas = $stmt->fetchAll();

        jsonResponse(200, [
            "ok" => true,
            "data" => $entradas
        ]);
    }

    // =========================
    // POST
    // =========================
    if ($method === 'POST') {
        $input = getJsonInput();
        $data = sanitizeEntradaPayload($input, false);

        $stmt = $conn->prepare("
            INSERT INTO entradas (
                nombre,
                descripcion,
                precio_base,
                cambio_automatico,
                hora_inicio_cambio,
                hora_fin_cambio,
                nuevo_precio,
                activo
            )
            VALUES (
                :nombre,
                :descripcion,
                :precio_base,
                :cambio_automatico,
                :hora_inicio_cambio,
                :hora_fin_cambio,
                :nuevo_precio,
                :activo
            )
            RETURNING
                id,
                nombre,
                descripcion,
                precio_base,
                cambio_automatico,
                nuevo_precio,
                fecha_creacion,
                activo,
                hora_inicio_cambio,
                hora_fin_cambio
        ");

        $stmt->execute([
            ':nombre' => $data['nombre'],
            ':descripcion' => $data['descripcion'],
            ':precio_base' => $data['precio_base'],
            ':cambio_automatico' => $data['cambio_automatico'] ?? false,
            ':hora_inicio_cambio' => $data['hora_inicio_cambio'],
            ':hora_fin_cambio' => $data['hora_fin_cambio'],
            ':nuevo_precio' => $data['nuevo_precio'],
            ':activo' => $data['activo'] ?? true,
        ]);

        $newEntrada = $stmt->fetch();

        jsonResponse(201, [
            "ok" => true,
            "message" => "Entrada creada correctamente.",
            "data" => $newEntrada
        ]);
    }

    // =========================
    // PUT
    // =========================
    if ($method === 'PUT') {
        if ($id === null || $id <= 0) {
            jsonResponse(400, [
                "ok" => false,
                "message" => "Debes enviar el parámetro 'id' en la URL."
            ]);
        }

        $check = $conn->prepare("SELECT id FROM entradas WHERE id = :id LIMIT 1");
        $check->execute([':id' => $id]);

        if (!$check->fetch()) {
            jsonResponse(404, [
                "ok" => false,
                "message" => "Entrada no encontrada."
            ]);
        }

        $input = getJsonInput();
        if (empty($input)) {
            jsonResponse(400, [
                "ok" => false,
                "message" => "Debes enviar datos para actualizar."
            ]);
        }

        $data = sanitizeEntradaPayload($input, true);

        $stmt = $conn->prepare("
            UPDATE entradas
            SET
                nombre = COALESCE(:nombre, nombre),
                descripcion = COALESCE(:descripcion, descripcion),
                precio_base = COALESCE(:precio_base, precio_base),
                cambio_automatico = COALESCE(:cambio_automatico, cambio_automatico),
                hora_inicio_cambio = CASE
                    WHEN :hora_inicio_cambio_set = 1 THEN :hora_inicio_cambio
                    ELSE hora_inicio_cambio
                END,
                hora_fin_cambio = CASE
                    WHEN :hora_fin_cambio_set = 1 THEN :hora_fin_cambio
                    ELSE hora_fin_cambio
                END,
                nuevo_precio = CASE
                    WHEN :nuevo_precio_set = 1 THEN :nuevo_precio
                    ELSE nuevo_precio
                END,
                activo = COALESCE(:activo, activo)
            WHERE id = :id
            RETURNING
                id,
                nombre,
                descripcion,
                precio_base,
                cambio_automatico,
                nuevo_precio,
                fecha_creacion,
                activo,
                hora_inicio_cambio,
                hora_fin_cambio
        ");

        $stmt->execute([
            ':id' => $id,
            ':nombre' => array_key_exists('nombre', $input) ? $data['nombre'] : null,
            ':descripcion' => array_key_exists('descripcion', $input) ? $data['descripcion'] : null,
            ':precio_base' => array_key_exists('precio_base', $input) ? $data['precio_base'] : null,
            ':cambio_automatico' => array_key_exists('cambio_automatico', $input) ? $data['cambio_automatico'] : null,

            ':hora_inicio_cambio_set' => array_key_exists('hora_inicio_cambio', $input) ? 1 : 0,
            ':hora_inicio_cambio' => $data['hora_inicio_cambio'],

            ':hora_fin_cambio_set' => array_key_exists('hora_fin_cambio', $input) ? 1 : 0,
            ':hora_fin_cambio' => $data['hora_fin_cambio'],

            ':nuevo_precio_set' => array_key_exists('nuevo_precio', $input) ? 1 : 0,
            ':nuevo_precio' => $data['nuevo_precio'],

            ':activo' => array_key_exists('activo', $input) ? $data['activo'] : null,
        ]);

        $updated = $stmt->fetch();

        jsonResponse(200, [
            "ok" => true,
            "message" => "Entrada actualizada correctamente.",
            "data" => $updated
        ]);
    }

    // =========================
    // DELETE
    // =========================
    if ($method === 'DELETE') {
        if ($id === null || $id <= 0) {
            jsonResponse(400, [
                "ok" => false,
                "message" => "Debes especificar el 'id' a eliminar."
            ]);
        }

        $stmt = $conn->prepare("
            DELETE FROM entradas
            WHERE id = :id
            RETURNING id, nombre
        ");
        $stmt->execute([':id' => $id]);

        $deleted = $stmt->fetch();

        if (!$deleted) {
            jsonResponse(404, [
                "ok" => false,
                "message" => "Entrada no encontrada."
            ]);
        }

        jsonResponse(200, [
            "ok" => true,
            "message" => "Entrada eliminada correctamente.",
            "data" => $deleted
        ]);
    }

    jsonResponse(405, [
        "ok" => false,
        "message" => "Método no permitido."
    ]);
} catch (PDOException $e) {
    jsonResponse(500, [
        "ok" => false,
        "message" => "Error de base de datos.",
        "error" => $e->getMessage()
    ]);
} catch (Throwable $e) {
    jsonResponse(500, [
        "ok" => false,
        "message" => "Error interno del servidor.",
        "error" => $e->getMessage()
    ]);
}
