<?php

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'error' => 'Método no permitido'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$envFilePath = __DIR__ . '/../.env';

if (file_exists($envFilePath)) {
    $envContent = file($envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($envContent as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);

        $key = trim($key);
        $value = trim($value);

        $value = trim($value, "\"'");

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

$host     = getenv('DB_HOST') ?: '';
$port     = getenv('DB_PORT') ?: '';
$dbname   = getenv('DB_NAME') ?: '';
$user     = getenv('DB_USER') ?: '';
$password = getenv('DB_PASSWORD') ?: '';

if ($host === '' || $port === '' || $dbname === '' || $user === '') {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Faltan variables de entorno necesarias',
        'details' => [
            'DB_HOST' => $host !== '' ? $host : 'no definido',
            'DB_PORT' => $port !== '' ? $port : 'no definido',
            'DB_NAME' => $dbname !== '' ? $dbname : 'no definido',
            'DB_USER' => $user !== '' ? $user : 'no definido',
            'DB_PASSWORD' => $password !== '' ? 'definido' : 'no definido'
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};connect_timeout=5";

    $conn = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Error al conectar a la base de datos',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'JSON inválido'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$telefono = isset($input['telefono']) ? trim((string)$input['telefono']) : '';
$clave    = isset($input['password']) ? (string)$input['password'] : '';

if ($telefono === '' || $clave === '') {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Teléfono y contraseña son obligatorios'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT
            u.id,
            u.telefono,
            u.nombre,
            u.email,
            u.rol_id,
            u.activo,
            u.clave_bcrypt,
            r.nombre AS rol_nombre
        FROM usuarios u
        LEFT JOIN roles r ON u.rol_id = r.id
        WHERE u.telefono = :telefono
        LIMIT 1
    ");

    $stmt->execute([
        ':telefono' => $telefono
    ]);

    $usuario = $stmt->fetch();

    if (!$usuario) {
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'error' => 'Credenciales inválidas'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $estaActivo = false;
    $valorActivo = $usuario['activo'] ?? null;

    if (is_bool($valorActivo)) {
        $estaActivo = $valorActivo;
    } elseif (is_numeric($valorActivo)) {
        $estaActivo = ((int)$valorActivo === 1);
    } elseif (is_string($valorActivo)) {
        $valorNormalizado = strtolower(trim($valorActivo));
        $estaActivo = in_array($valorNormalizado, ['1', 't', 'true', 'on', 'si', 'sí', 'yes'], true);
    }

    if (!$estaActivo) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'error' => 'El usuario no está activo'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (empty($usuario['clave_bcrypt']) || !password_verify($clave, (string)$usuario['clave_bcrypt'])) {
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'error' => 'Credenciales inválidas'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $rolNombre = isset($usuario['rol_nombre']) ? trim((string)$usuario['rol_nombre']) : '';
    $rolSlug = $rolNombre !== ''
        ? (function_exists('mb_strtolower') ? mb_strtolower($rolNombre, 'UTF-8') : strtolower($rolNombre))
        : '';

    $mapaRoles = [
        'administrador' => 'admin',
        'admin'         => 'admin',
        'vendedor'      => 'vendedor',
        'seller'        => 'vendedor',
        'promotor'      => 'promotor',
        'promoter'      => 'promotor',
    ];

    if ($rolSlug !== '' && isset($mapaRoles[$rolSlug])) {
        $rolSlug = $mapaRoles[$rolSlug];
    }

    if ($rolSlug === 'promotor') {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'error' => 'Los promotores no tienen acceso al sistema'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    unset($usuario['clave_bcrypt']);

    $usuario['rol_slug'] = $rolSlug !== '' ? $rolSlug : null;
    $usuario['activo'] = (bool)$estaActivo;

    $token = bin2hex(random_bytes(32));

    echo json_encode([
        'ok' => true,
        'token' => $token,
        'user' => $usuario
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Error al validar las credenciales',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
