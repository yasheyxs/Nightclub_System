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

function isAdminRole(string $roleName): bool
{
    return strpos(strtolower(trim($roleName)), 'admin') !== false;
}

try {
    $globalStart = microtime(true);

    $timer = microtime(true);
    $pdo = getPdo();
    logTime('PDO connect/reuse', $timer);

    $method = $_SERVER['REQUEST_METHOD'];

    // ============================
    // GET OPTIMIZADO
    // ============================
    if ($method === 'GET') {
        $timer = microtime(true);

        $stmt = $pdo->query("
            SELECT
                u.id,
                u.nombre,
                u.telefono,
                u.email,
                u.rol_id,
                u.activo,
                u.fecha_creacion,
                COALESCE(r.nombre, '') AS rol_nombre
            FROM usuarios u
            LEFT JOIN roles r ON r.id = u.rol_id
            ORDER BY u.id ASC
        ");

        $usuarios = $stmt->fetchAll() ?: [];
        logTime('GET usuarios query', $timer);

        logTime('TOTAL usuarios.php', $globalStart);
        jsonResponse(200, $usuarios);
    }

    // ============================
    // POST OPTIMIZADO
    // ============================
    if ($method === 'POST') {
        $input = getJsonInput();

        if (
            empty($input['nombre']) ||
            empty($input['telefono']) ||
            empty($input['rolId'])
        ) {
            jsonResponse(400, ['error' => 'Faltan datos obligatorios']);
        }

        $nombre = trim((string)$input['nombre']);
        $telefono = trim((string)$input['telefono']);
        $email = $input['email'] ?? null;
        $rolId = (int)$input['rolId'];
        $activo = isset($input['activo']) ? (bool)$input['activo'] : true;
        $password = trim((string)($input['password'] ?? ''));

        if ($password === '' || strlen($password) < 8) {
            jsonResponse(400, ['error' => 'Password inválido']);
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $timer = microtime(true);
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (
                nombre,
                telefono,
                email,
                rol_id,
                activo,
                fecha_creacion,
                clave_bcrypt
            )
            VALUES (
                :nombre,
                :telefono,
                :email,
                :rol_id,
                :activo,
                NOW(),
                :clave
            )
            RETURNING id, nombre, telefono, email, rol_id, activo, fecha_creacion
        ");
        $stmt->execute([
            ':nombre' => $nombre,
            ':telefono' => $telefono,
            ':email' => $email,
            ':rol_id' => $rolId,
            ':activo' => $activo,
            ':clave' => $passwordHash,
        ]);
        $usuario = $stmt->fetch();
        logTime('POST usuario insert', $timer);

        logTime('TOTAL usuarios.php', $globalStart);
        jsonResponse(200, $usuario ?: []);
    }

    // ============================
    // PUT OPTIMIZADO
    // ============================
    if ($method === 'PUT') {
        if (!isset($_GET['id'])) {
            jsonResponse(400, ['error' => 'ID requerido']);
        }

        $id = (int)$_GET['id'];
        if ($id <= 0) {
            jsonResponse(400, ['error' => 'ID inválido']);
        }

        $input = getJsonInput();

        $timer = microtime(true);
        $stmt = $pdo->prepare("
            SELECT id, clave_bcrypt
            FROM usuarios
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        logTime('PUT fetch usuario', $timer);

        if ($user === false) {
            jsonResponse(404, ['error' => 'Usuario no encontrado']);
        }

        $nombre = $input['nombre'] ?? null;
        $telefono = $input['telefono'] ?? null;
        $email = $input['email'] ?? null;
        $rolId = $input['rolId'] ?? null;
        $activo = isset($input['activo']) ? (bool)$input['activo'] : null;

        $newPassword = trim((string)($input['newPassword'] ?? ''));
        $currentPassword = trim((string)($input['currentPassword'] ?? ''));

        $updatePassword = false;
        $newHash = null;

        if ($newPassword !== '' || $currentPassword !== '') {
            if ($newPassword === '' || $currentPassword === '') {
                jsonResponse(400, ['error' => 'Password incompleto']);
            }

            if (!password_verify($currentPassword, $user['clave_bcrypt'])) {
                jsonResponse(401, ['error' => 'Password incorrecto']);
            }

            if (strlen($newPassword) < 8) {
                jsonResponse(400, ['error' => 'Password corto']);
            }

            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $updatePassword = true;
        }

        $sql = "
            UPDATE usuarios SET
                nombre = COALESCE(:nombre, nombre),
                telefono = COALESCE(:telefono, telefono),
                email = COALESCE(:email, email),
                rol_id = COALESCE(:rol_id, rol_id),
                activo = COALESCE(:activo, activo)
        ";

        $params = [
            ':id' => $id,
            ':nombre' => $nombre,
            ':telefono' => $telefono,
            ':email' => $email,
            ':rol_id' => $rolId,
            ':activo' => $activo,
        ];

        if ($updatePassword && $newHash) {
            $sql .= ", clave_bcrypt = :clave";
            $params[':clave'] = $newHash;
        }

        $sql .= " WHERE id = :id RETURNING id, nombre, telefono, email, rol_id, activo, fecha_creacion";

        $timer = microtime(true);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $updated = $stmt->fetch();
        logTime('PUT update usuario', $timer);

        logTime('TOTAL usuarios.php', $globalStart);
        jsonResponse(200, $updated ?: []);
    }

    // ============================
    // DELETE OPTIMIZADO
    // ============================
    if ($method === 'DELETE') {
        if (!isset($_GET['id'])) {
            jsonResponse(400, ['error' => 'ID requerido']);
        }

        $id = (int)$_GET['id'];
        if ($id <= 0) {
            jsonResponse(400, ['error' => 'ID inválido']);
        }

        $input = getJsonInput();
        $password = trim((string)($input['password'] ?? ''));

        $timer = microtime(true);
        $stmt = $pdo->prepare("
            SELECT
                u.id,
                u.clave_bcrypt,
                COALESCE(r.nombre, '') AS rol_nombre
            FROM usuarios u
            LEFT JOIN roles r ON r.id = u.rol_id
            WHERE u.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        logTime('DELETE fetch usuario', $timer);

        if ($user === false) {
            jsonResponse(404, ['error' => 'Usuario no encontrado']);
        }

        if (isAdminRole($user['rol_nombre'])) {
            $timer = microtime(true);
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM usuarios u
                LEFT JOIN roles r ON r.id = u.rol_id
                WHERE u.id <> :id
                  AND u.activo = TRUE
                  AND LOWER(COALESCE(r.nombre,'')) LIKE '%admin%'
            ");
            $stmt->execute([':id' => $id]);
            $count = (int)$stmt->fetchColumn();
            logTime('DELETE count admins', $timer);

            if ($count === 0) {
                jsonResponse(400, ['error' => 'Debe existir al menos un admin']);
            }

            if ($password === '' || !password_verify($password, $user['clave_bcrypt'])) {
                jsonResponse(401, ['error' => 'Password incorrecto']);
            }
        }

        $timer = microtime(true);
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $id]);
        logTime('DELETE usuario', $timer);

        logTime('TOTAL usuarios.php', $globalStart);
        jsonResponse(200, ['success' => true]);
    }

    jsonResponse(405, ['error' => 'Método no permitido']);
} catch (Throwable $e) {
    error_log('usuarios.php ERROR: ' . $e->getMessage());

    jsonResponse(500, [
        'error' => 'Error interno',
        'detalle' => $e->getMessage(),
    ]);
}
