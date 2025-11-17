<?php
// === CORS ===
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// === CONEXIÓN A SUPABASE ===
$host = "aws-1-us-east-2.pooler.supabase.com";
$port = "5432";
$dbname = "postgres";
$user = "postgres.kxvogvgsgwfvtmidabyp";
$password = "lapicero30!";

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password;sslmode=require");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al conectar a la base: ' . $e->getMessage()]);
    exit;
}

// === DETERMINAR MÉTODO ===
$method = $_SERVER['REQUEST_METHOD'];

function respondWithError(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

function readJsonBody(): array
{
    $body = file_get_contents("php://input");
    if ($body === false || $body === '') {
        return [];
    }
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : [];
}

function isAdminRoleName(?string $roleName): bool
{
    if (!$roleName) {
        return false;
    }
    $normalized = trim($roleName);
    if ($normalized === '') {
        return false;
    }
    if (function_exists('mb_strtolower')) {
        $normalized = mb_strtolower($normalized, 'UTF-8');
    } else {
        $normalized = strtolower($normalized);
    }
    return strpos($normalized, 'admin') !== false;
}

switch ($method) {

    // LISTAR USUARIOS
    case 'GET':
        $stmt = $conn->query("SELECT * FROM usuarios ORDER BY id ASC");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($usuarios);
        break;

    // CREAR USUARIO
    case 'POST':
        $input = readJsonBody();

        if (!$input || empty($input['nombre']) || empty($input['telefono']) || empty($input['rolId'])) {
            respondWithError(400, 'Faltan datos obligatorios.');
        }

        $nombre = $input['nombre'];
        $telefono = $input['telefono'];
        $email = $input['email'] ?? null;
        $rolId = $input['rolId'];
        $activo = isset($input['activo']) ? (int)$input['activo'] : 1;
        $password = isset($input['password']) ? trim($input['password']) : '';

        if ($password === '') {
            respondWithError(400, 'La contraseña es obligatoria para crear un usuario.');
        }

        if (strlen($password) < 8) {
            respondWithError(400, 'La contraseña debe tener al menos 8 caracteres.');
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $conn->prepare("INSERT INTO usuarios (nombre, telefono, email, rol_id, activo, fecha_creacion, clave_bcrypt)
                                VALUES (:nombre, :telefono, :email, :rol_id, :activo, NOW(), :clave_bcrypt)
                                RETURNING *");
        $stmt->execute([
            ':nombre' => $nombre,
            ':telefono' => $telefono,
            ':email' => $email,
            ':rol_id' => $rolId,
            ':activo' => $activo,
            ':clave_bcrypt' => $passwordHash,
        ]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($usuario);
        break;

    // ACTUALIZAR USUARIO
    case 'PUT':
        if (!isset($_GET['id'])) {
            respondWithError(400, 'Falta el parámetro id');
        }
        $id = (int)$_GET['id'];
        $input = readJsonBody();

        $stmtCurrent = $conn->prepare("SELECT id, clave_bcrypt FROM usuarios WHERE id = :id LIMIT 1");
        $stmtCurrent->execute([':id' => $id]);
        $currentUser = $stmtCurrent->fetch(PDO::FETCH_ASSOC);

        if (!$currentUser) {
            respondWithError(404, 'Usuario no encontrado');
        }

        $nombre = $input['nombre'] ?? null;
        $telefono = $input['telefono'] ?? null;
        $email = $input['email'] ?? null;
        $rolId = $input['rolId'] ?? null;
        $activo = isset($input['activo']) ? (int)$input['activo'] : 1;
        $currentPassword = isset($input['currentPassword']) ? trim($input['currentPassword']) : '';
        $newPassword = isset($input['newPassword']) ? trim($input['newPassword']) : '';
        $shouldUpdatePassword = false;
        $newPasswordHash = null;

        if ($newPassword !== '' || $currentPassword !== '') {
            if ($currentPassword === '' || $newPassword === '') {
                respondWithError(400, 'Debes ingresar la contraseña actual y una nueva para actualizarla.');
            }

            if (strlen($newPassword) < 8) {
                respondWithError(400, 'La nueva contraseña debe tener al menos 8 caracteres.');
            }

            $storedHash = $currentUser['clave_bcrypt'] ?? null;
            if (!$storedHash) {
                respondWithError(400, 'No se puede actualizar la contraseña porque el usuario no tiene una contraseña registrada.');
            }

            if (!password_verify($currentPassword, $storedHash)) {
                respondWithError(401, 'La contraseña actual no es correcta. Si no la recuerdas utiliza "Olvidé mi contraseña".');
            }

            if (password_verify($newPassword, $storedHash)) {
                respondWithError(400, 'La nueva contraseña no puede ser igual a la contraseña actual.');
            }

            $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $shouldUpdatePassword = true;
        }

        $sql = "UPDATE usuarios
                                SET nombre = :nombre, telefono = :telefono, email = :email, rol_id = :rol_id, activo = :activo";
        $params = [
            ':nombre' => $nombre,
            ':telefono' => $telefono,
            ':email' => $email,
            ':rol_id' => $rolId,
            ':activo' => $activo,
            ':id' => $id
        ];

        if ($shouldUpdatePassword && $newPasswordHash) {
            $sql .= ", clave_bcrypt = :clave_bcrypt";
            $params[':clave_bcrypt'] = $newPasswordHash;
        }

        $sql .= " WHERE id = :id RETURNING *";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($usuario);
        break;

    // ELIMINAR USUARIO
    case 'DELETE':
        if (!isset($_GET['id'])) {
            respondWithError(400, 'Falta el parámetro id');
        }
        $id = (int)$_GET['id'];
        $input = readJsonBody();
        $passwordProvided = isset($input['password']) ? trim($input['password']) : '';

        $stmtUser = $conn->prepare("SELECT u.id, u.rol_id, u.activo, u.clave_bcrypt, COALESCE(r.nombre, '') AS rol_nombre
                                     FROM usuarios u
                                     LEFT JOIN roles r ON u.rol_id = r.id
                                     WHERE u.id = :id LIMIT 1");
        $stmtUser->execute([':id' => $id]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            respondWithError(404, 'Usuario no encontrado');
        }

        $isAdmin = isAdminRoleName($user['rol_nombre'] ?? '');

        if ($isAdmin) {
            $stmtAdminCount = $conn->prepare("SELECT COUNT(*)
                FROM usuarios u
                LEFT JOIN roles r ON u.rol_id = r.id
                WHERE u.id <> :id AND u.activo = true AND LOWER(COALESCE(r.nombre, '')) LIKE '%admin%'");
            $stmtAdminCount->execute([':id' => $id]);
            $remainingAdmins = (int)$stmtAdminCount->fetchColumn();

            if ($remainingAdmins === 0) {
                respondWithError(400, 'Debe existir al menos un administrador activo en el sistema.');
            }

            if ($passwordProvided === '') {
                respondWithError(400, 'Debes ingresar la contraseña del administrador antes de eliminarlo.');
            }

            $hash = $user['clave_bcrypt'] ?? null;
            if (!$hash || !password_verify($passwordProvided, $hash)) {
                respondWithError(401, 'La contraseña no coincide. Si no la recuerdas utiliza "Olvidé mi contraseña".');
            }
        }

        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['message' => 'Usuario eliminado correctamente']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
        break;
}
