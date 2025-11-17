<?php
// Mostrar errores en el navegador para depuración
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- CORS ---
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
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// **Nuevo enfoque**: Mostrar el contenido del archivo .env para depuración
$envFilePath = __DIR__ . '/../.env';

// Verificar si el archivo .env existe y mostrar su contenido
$envExists = file_exists($envFilePath);

// Cargar las variables de entorno manualmente sin phpdotenv
if ($envExists) {
    $envContent = file_get_contents($envFilePath);
    foreach (explode("\n", $envContent) as $line) {
        // Solo cargar líneas que tengan '='
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));  // Establecer la variable de entorno manualmente
        }
    }
}

// Verificar que las variables de entorno se cargaron correctamente
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$dbname = getenv('DB_NAME');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');

// Si alguna de las variables es false, muestra un error detallado
if (!$host || !$port || !$dbname || !$user || !$password) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Faltan variables de entorno necesarias',
        'details' => [
            'DB_HOST' => $host ? $host : 'no definido',
            'DB_PORT' => $port ? $port : 'no definido',
            'DB_NAME' => $dbname ? $dbname : 'no definido',
            'DB_USER' => $user ? $user : 'no definido',
            'DB_PASSWORD' => $password ? 'definido' : 'no definido'
        ]
    ]);
    exit;
}

// Conexión a la base de datos
try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password;sslmode=require");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al conectar a la base de datos', 'details' => $e->getMessage()]);
    exit;
}

// Obtener datos de la solicitud (esperamos un email)
$data = json_decode(file_get_contents('php://input'), true);
$email = strtolower(trim($data["email"] ?? ""));  // Usamos solo el email

if ($email === "") {
    http_response_code(400);
    echo json_encode(['error' => 'El email es obligatorio']);
    exit;
}

// --- Buscar usuario por email ---
$stmt = $conn->prepare("SELECT id, nombre FROM usuarios WHERE email = :email LIMIT 1");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'No existe un usuario con ese email']);
    exit;
}

// --- Generar nueva contraseña ---
$newPass = strtoupper(bin2hex(random_bytes(4)));  // Generar nueva contraseña
$hash = password_hash($newPass, PASSWORD_BCRYPT);

$stmt = $conn->prepare("UPDATE usuarios SET clave_bcrypt = :c WHERE id = :id");
$stmt->execute([':c' => $hash, ':id' => $user['id']]);

// --- Enviar Mail con Brevo ---
$payload = [
    "sender" => [
        "name" => getenv('MAILER_FROM_NAME'),
        "email" => getenv('MAILER_FROM_ADDRESS')
    ],
    "to" => [[
        "email" => $email,
        "name" => $user["nombre"]
    ]],
    "subject" => "Recuperación de contraseña - Santas Club",
    "htmlContent" =>
    "<p>Hola <strong>{$user['nombre']}</strong>,</p>
         <p>Tu nueva contraseña temporal es:</p>
         <h2>$newPass</h2>
         <p>Por seguridad, cámbiala al ingresar al sistema.</p>"
];

$ch = curl_init("https://api.brevo.com/v3/smtp/email");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Accept: application/json",
    "Content-Type: application/json",
    "api-key: " . getenv('BREVO_API_KEY')
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status >= 400) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo enviar el correo']);
    exit;
}

echo json_encode(['message' => 'Contraseña temporal enviada al correo']);
