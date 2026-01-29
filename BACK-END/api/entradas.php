<?php
require_once __DIR__ . '/cors.php';

// === CONEXIÓN SUPABASE ===
$host = "aws-1-us-east-2.pooler.supabase.com";
$port = "5432";
$dbname = "postgres";
$user = "postgres.kxvogvgsgwfvtmidabyp";
$password = "lapicero30!";

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password;sslmode=require");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $method = $_SERVER['REQUEST_METHOD'];

    // ====== GET: listar todas las entradas ======
    if ($method === 'GET') {
        $stmt = $conn->query("SELECT * FROM entradas ORDER BY id ASC");
        $entradas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($entradas);
        exit;
    }

    // ====== PUT: actualizar una entrada existente ======
    if ($method === 'PUT') {
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(["error" => "Falta parámetro ID en la URL."]);
            exit;
        }

        $id = (int) $_GET['id'];
        $input = json_decode(file_get_contents("php://input"), true);

        if (!$input) {
            http_response_code(400);
            echo json_encode(["error" => "Datos inválidos o cuerpo vacío."]);
            exit;
        }

        $stmt = $conn->prepare("
            UPDATE entradas
            SET
                nombre = COALESCE(:nombre, nombre),
                descripcion = COALESCE(:descripcion, descripcion),
                precio_base = COALESCE(:precio_base, precio_base),
                cambio_automatico = COALESCE(:cambio_automatico, cambio_automatico),
                hora_inicio_cambio = :hora_inicio_cambio,
                hora_fin_cambio = :hora_fin_cambio,
                nuevo_precio = :nuevo_precio,
                activo = COALESCE(:activo, activo)
            WHERE id = :id
            RETURNING *;
        ");

        $stmt->execute([
            ':id' => $id,
            ':nombre' => $input['nombre'] ?? null,
            ':descripcion' => $input['descripcion'] ?? null,
            ':precio_base' => $input['precio_base'] ?? null,
            ':cambio_automatico' => isset($input['cambio_automatico']) ? (int)$input['cambio_automatico'] : null,
            ':hora_inicio_cambio' => $input['hora_inicio_cambio'] ?? null,
            ':hora_fin_cambio' => $input['hora_fin_cambio'] ?? null,
            ':nuevo_precio' => $input['nuevo_precio'] ?? null,
            ':activo' => isset($input['activo']) ? (int)$input['activo'] : null,
        ]);

        $updated = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($updated);
        exit;
    }

    // ====== POST: crear nueva entrada ======
    if ($method === 'POST') {
        $input = json_decode(file_get_contents("php://input"), true);
        if (!$input || !isset($input['nombre']) || !isset($input['precio_base'])) {
            http_response_code(400);
            echo json_encode(["error" => "Faltan campos obligatorios."]);
            exit;
        }

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
            ) VALUES (
                :nombre,
                :descripcion,
                :precio_base,
                :cambio_automatico,
                :hora_inicio_cambio,
                :hora_fin_cambio,
                :nuevo_precio,
                :activo
            )
            RETURNING *;
        ");

        $stmt->execute([
            ':nombre' => $input['nombre'],
            ':descripcion' => $input['descripcion'] ?? null,
            ':precio_base' => $input['precio_base'],
            ':cambio_automatico' => isset($input['cambio_automatico']) ? (int)$input['cambio_automatico'] : 0,
            ':hora_inicio_cambio' => $input['hora_inicio_cambio'] ?? null,
            ':hora_fin_cambio' => $input['hora_fin_cambio'] ?? null,
            ':nuevo_precio' => $input['nuevo_precio'] ?? null,
            ':activo' => isset($input['activo']) ? (int)$input['activo'] : 1,
        ]);

        $newEntrada = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($newEntrada);
        exit;
    }

    // ====== DELETE: eliminar una entrada ======
    if ($method === 'DELETE') {
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(["error" => "Debe especificar el ID a eliminar."]);
            exit;
        }

        $id = (int) $_GET['id'];
        $stmt = $conn->prepare("DELETE FROM entradas WHERE id = :id");
        $stmt->execute([':id' => $id]);

        echo json_encode(["success" => true]);
        exit;
    }

    // ====== MÉTODO NO SOPORTADO ======
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido."]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
