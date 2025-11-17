<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$host = "aws-1-us-east-2.pooler.supabase.com";
$port = "5432";
$dbname = "postgres";
$user = "postgres.kxvogvgsgwfvtmidabyp";
$password = "lapicero30!";

try {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password;sslmode=require");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $method = $_SERVER['REQUEST_METHOD'];

    // =====================================================
    //  GET: Devuelve todos los usuarios con sus invitados
    // =====================================================
    if ($method === 'GET') {
        $sql = "
            SELECT 
                u.id AS usuario_id,
                u.nombre AS usuario_nombre,
                u.telefono AS usuario_telefono,
                u.email AS usuario_email,
                u.rol_id AS usuario_rol_id,
                CASE 
                    WHEN u.rol_id = 1 THEN 'owner'
                    ELSE 'promoter'
                END AS usuario_rol,
                COALESCE(
                    json_agg(
                        json_build_object(
                            'id', l.id,
                            'nombre_persona', l.nombre_persona,
                            'telefono', l.telefono,
                            'ingreso', l.ingreso,
                            'fecha_registro', l.fecha_registro
                        ) ORDER BY l.id
                    ) FILTER (WHERE l.id IS NOT NULL),
                    '[]'::json
                ) AS invitados
            FROM usuarios u
            LEFT JOIN listas l ON l.usuario_id = u.id
            WHERE u.activo = TRUE
            GROUP BY u.id
            ORDER BY u.id;
        ";

        $stmt = $conn->query($sql);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Aseguramos que "invitados" sea array real
        foreach ($result as &$row) {
            if (is_string($row['invitados'])) {
                $decoded = json_decode($row['invitados'], true);
                $row['invitados'] = is_array($decoded) ? $decoded : [];
            }
        }

        echo json_encode($result);
        exit;
    }

    // =====================================================
    //  POST: Agregar nuevo invitado
    // =====================================================
    if ($method === 'POST') {
        $input = json_decode(file_get_contents("php://input"), true);

        if (!isset($input['usuario_id']) || !isset($input['nombre_persona'])) {
            http_response_code(400);
            echo json_encode(["error" => "Campos obligatorios faltantes."]);
            exit;
        }

        $userStmt = $conn->prepare("SELECT activo FROM usuarios WHERE id = :id LIMIT 1");
        $userStmt->execute([':id' => $input['usuario_id']]);
        $usuario = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            http_response_code(404);
            echo json_encode(["error" => "El usuario especificado no existe."]);
            exit;
        }

        $rawActivo = $usuario['activo'] ?? null;
        $isActive = false;
        if (is_bool($rawActivo)) {
            $isActive = $rawActivo;
        } elseif (is_numeric($rawActivo)) {
            $isActive = ((int) $rawActivo) === 1;
        } elseif (is_string($rawActivo)) {
            $isActive = in_array(strtolower($rawActivo), ['t', 'true', '1', 'yes', 'on'], true);
        }

        if (!$isActive) {
            http_response_code(403);
            echo json_encode(["error" => "El usuario está inactivo y no puede gestionar listas."]);
            exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO listas (usuario_id, nombre_persona, telefono)
            VALUES (:usuario_id, :nombre_persona, :telefono)
            RETURNING id, nombre_persona, telefono;
        ");

        $stmt->execute([
            ':usuario_id' => $input['usuario_id'],
            ':nombre_persona' => $input['nombre_persona'],
            ':telefono' => $input['telefono'] ?? null,
        ]);

        $newGuest = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($newGuest);
        exit;
    }

    // =====================================================
    //  PUT: Actualizar invitado existente
    // =====================================================
    if ($method === 'PUT') {
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(["error" => "Debe especificar el ID en la URL."]);
            exit;
        }

        $id = (int) $_GET['id'];
        $input = json_decode(file_get_contents("php://input"), true);

        $stmt = $conn->prepare("
            UPDATE listas
            SET 
                nombre_persona = COALESCE(:nombre_persona, nombre_persona),
                telefono = COALESCE(:telefono, telefono)
            WHERE id = :id
            RETURNING id, nombre_persona, telefono;
        ");

        $stmt->execute([
            ':id' => $id,
            ':nombre_persona' => $input['nombre_persona'] ?? null,
            ':telefono' => $input['telefono'] ?? null,
        ]);

        $updated = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($updated);
        exit;
    }

    // =====================================================
    //  DELETE: Eliminar invitado
    // =====================================================
    if ($method === 'DELETE') {
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(["error" => "Debe especificar el ID a eliminar."]);
            exit;
        }

        $id = (int) $_GET['id'];
        $stmt = $conn->prepare("DELETE FROM listas WHERE id = :id");
        $stmt->execute([':id' => $id]);

        echo json_encode(["success" => true]);
        exit;
    }

    // =====================================================
    //  MÉTODO NO SOPORTADO
    // =====================================================
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido."]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
