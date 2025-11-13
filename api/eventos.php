<?php
// ======================================
// CONFIGURACIÓN GENERAL
// ======================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

date_default_timezone_set('America/Argentina/Cordoba');

// ======================================
// CONEXIÓN A BASE DE DATOS
// ======================================
$host = "aws-1-us-east-2.pooler.supabase.com";
$port = "5432";
$dbname = "postgres";
$user = "postgres.kxvogvgsgwfvtmidabyp";
$password = "lapicero30!";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password;sslmode=require");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $method = $_SERVER['REQUEST_METHOD'];

    // ===========================================================
    // FUNCIÓN: Desactivar eventos ya ocurridos
    // ===========================================================
    function desactivar_eventos_pasados(PDO $pdo): void
    {
        $pdo->exec("UPDATE eventos SET activo = false WHERE fecha < NOW() AND activo = true");
    }

    // ===========================================================
    // FUNCIÓN: Generar automáticamente los próximos 5 sábados
    // ===========================================================
    function generar_sabados(PDO $pdo, int $cantidad = 5, int $cupo = 1000): array
    {
        $hoy = new DateTime('today');
        $w = (int)$hoy->format('w');
        // 0=domingo, 6=sábado → mover al próximo sábado
        $dias = (6 - $w + 7) % 7;
        $hoy->modify("+$dias day");

        $eventos = [];

        $consultaExistente = $pdo->prepare("
            SELECT id, nombre, detalle, fecha, capacidad, activo
            FROM eventos
            WHERE fecha::date = :fecha::date AND activo = true
            ORDER BY fecha ASC
            LIMIT 1
        ");

        $insert = $pdo->prepare("
            INSERT INTO eventos (nombre, detalle, fecha, capacidad, activo, fecha_creacion)
            VALUES (:nombre, :detalle, :fecha, :capacidad, true, NOW())
            ON CONFLICT (fecha) DO NOTHING
            RETURNING id, nombre, detalle, fecha, capacidad, activo
        ");

        for ($i = 0; $i < $cantidad; $i++) {
            $fechaDia = $hoy->format('Y-m-d');
            $consultaExistente->execute([':fecha' => $fechaDia]);
            $manual = $consultaExistente->fetch(PDO::FETCH_ASSOC);
            $consultaExistente->closeCursor();

            if ($manual) {
                $eventos[] = $manual;
                $hoy->modify('+7 day');
                continue;
            }

            $fecha = $hoy->format('Y-m-d 23:00:00');

            $insert->execute([
                ':nombre' => 'Evento',
                ':detalle' => null,
                ':fecha' => $fecha,
                ':capacidad' => $cupo
            ]);

            $evento = $insert->fetch(PDO::FETCH_ASSOC);
            $insert->closeCursor();

            if (!$evento) {
                $consultaExistente->execute([':fecha' => $fechaDia]);
                $evento = $consultaExistente->fetch(PDO::FETCH_ASSOC);
                $consultaExistente->closeCursor();
            }

            if ($evento) {
                $eventos[] = $evento;
            }

            $hoy->modify('+7 day');
        }

        usort($eventos, fn($a, $b) => strcmp($a['fecha'], $b['fecha']));
        return $eventos;
    }

    // ===========================================================
    // FUNCIÓN: Mapeo uniforme
    // ===========================================================
    function map_event($e)
    {
        return [
            'id' => (int)$e['id'],
            'nombre' => $e['nombre'],
            'detalle' => $e['detalle'],
            'fecha' => $e['fecha'],
            'capacidad' => (int)$e['capacidad'],
            'activo' => (bool)$e['activo']
        ];
    }

    desactivar_eventos_pasados($pdo);

    // ===========================================================
    // MÉTODOS PRINCIPALES
    // ===========================================================
    if ($method === 'GET') {
        // Modo calendario o próximos sábados
        $upcoming = isset($_GET['upcoming']) && $_GET['upcoming'] === '1';
        $calendar = isset($_GET['calendar']) && $_GET['calendar'] === '1';

        if ($upcoming) {
            $eventos = generar_sabados($pdo, 5, 1000);
            echo json_encode(array_map('map_event', $eventos));
            exit;
        }

        if ($calendar) {
            generar_sabados($pdo, 5, 1000);
            $desde = (new DateTime('today'))->format('Y-m-d');
            $hasta = (new DateTime('+60 days'))->format('Y-m-d');

            $stmt = $pdo->prepare("
                SELECT id, nombre, detalle, fecha, capacidad, activo
                FROM eventos
                WHERE fecha::date BETWEEN :desde AND :hasta
                AND activo = true
                ORDER BY fecha ASC
            ");
            $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
            $data = array_map('map_event', $stmt->fetchAll(PDO::FETCH_ASSOC));
            echo json_encode($data);
            exit;
        }

        // Listado general
        $stmt = $pdo->query("
            SELECT id, nombre, detalle, fecha, capacidad, activo
            FROM eventos
            WHERE activo = true AND fecha >= NOW()
            ORDER BY fecha ASC
        ");
        echo json_encode(array_map('map_event', $stmt->fetchAll(PDO::FETCH_ASSOC)));
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents("php://input"), true);
        if (!$input || !isset($input['nombre']) || !isset($input['fecha']) || !isset($input['capacidad'])) {
            http_response_code(400);
            echo json_encode(["error" => "Campos obligatorios: nombre, fecha, capacidad"]);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO eventos (nombre, detalle, fecha, capacidad, activo, fecha_creacion)
            VALUES (:nombre, :detalle, :fecha, :capacidad, true, NOW())
            RETURNING id, nombre, detalle, fecha, capacidad, activo
        ");
        $stmt->execute([
            ':nombre' => $input['nombre'],
            ':detalle' => $input['detalle'] ?? null,
            ':fecha' => $input['fecha'],
            ':capacidad' => (int)$input['capacidad']
        ]);

        echo json_encode(map_event($stmt->fetch(PDO::FETCH_ASSOC)));
        exit;
    }

    if ($method === 'PUT') {
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(["error" => "Debe especificar el ID en la URL"]);
            exit;
        }

        $id = (int) $_GET['id'];
        $input = json_decode(file_get_contents("php://input"), true);

        $stmt = $pdo->prepare("
            UPDATE eventos
            SET
                nombre = COALESCE(:nombre, nombre),
                detalle = COALESCE(:detalle, detalle),
                capacidad = COALESCE(:capacidad, capacidad)
            WHERE id = :id
            RETURNING id, nombre, detalle, fecha, capacidad, activo
        ");
        $stmt->execute([
            ':id' => $id,
            ':nombre' => $input['nombre'] ?? null,
            ':detalle' => $input['detalle'] ?? null,
            ':capacidad' => isset($input['capacidad']) ? (int)$input['capacidad'] : null,
        ]);

        echo json_encode(map_event($stmt->fetch(PDO::FETCH_ASSOC)));
        exit;
    }

    if ($method === 'DELETE') {
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(["error" => "Debe especificar el ID"]);
            exit;
        }
        $id = (int)$_GET['id'];
        $pdo->prepare("UPDATE eventos SET activo = false WHERE id = :id")->execute([':id' => $id]);
        echo json_encode(["success" => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(["error" => "Método no permitido"]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
