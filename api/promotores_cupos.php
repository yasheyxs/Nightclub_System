<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

date_default_timezone_set('America/Argentina/Cordoba');

$host = "aws-1-us-east-2.pooler.supabase.com";
$port = "5432";
$dbname = "postgres";
$user = "postgres.kxvogvgsgwfvtmidabyp";
$password = "lapicero30!";

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password;sslmode=require"
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS promotores_cupos (
            id SERIAL PRIMARY KEY,
            usuario_id INTEGER NOT NULL,
            evento_id INTEGER NOT NULL,
            entrada_id INTEGER NOT NULL,
            cupo_total INTEGER NOT NULL DEFAULT 50,
            cupo_vendido INTEGER NOT NULL DEFAULT 0,
            fecha_creacion TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW()
        );
    SQL);

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $eventoId = isset($_GET['evento_id']) ? (int) $_GET['evento_id'] : null;
        $entradaId = isset($_GET['entrada_id']) ? (int) $_GET['entrada_id'] : null;

        if (!$eventoId || !$entradaId) {
            http_response_code(400);
            echo json_encode(['error' => 'Debe indicar evento_id y entrada_id.']);
            exit;
        }

        $usuariosStmt = $pdo->query(<<<SQL
        SELECT
                u.id AS usuario_id,
                u.nombre AS usuario_nombre
            FROM usuarios u
            WHERE u.activo = true
            AND LOWER(TRIM(u.rol)) = 'promotor'
            ORDER BY u.nombre ASC
        SQL);

        $usuarios = $usuariosStmt->fetchAll();

        if (!$usuarios) {
            echo json_encode([]);
            exit;
        }

        $usuarioIds = array_map(
            static fn(array $row): int => (int) $row['usuario_id'],
            $usuarios
        );

        $cuposPorUsuario = [];

        if (count($usuarioIds) > 0) {
            $placeholders = implode(',', array_fill(0, count($usuarioIds), '?'));

            $cuposStmt = $pdo->prepare(
                "SELECT id, usuario_id, cupo_total, cupo_vendido
                 FROM promotores_cupos
                 WHERE evento_id = ?
                   AND entrada_id = ?
                   AND usuario_id IN ($placeholders)
                 ORDER BY id ASC"
            );

            $cuposStmt->execute(array_merge([$eventoId, $entradaId], $usuarioIds));
            $cuposRows = $cuposStmt->fetchAll();

            foreach ($cuposRows as $row) {
                $cuposPorUsuario[(int) $row['usuario_id']] = [
                    'id' => (int) $row['id'],
                    'cupo_total' => (int) $row['cupo_total'],
                    'cupo_vendido' => (int) $row['cupo_vendido'],
                ];
            }
        }

        $response = [];

        foreach ($usuarios as $usuario) {
            $userId = (int) $usuario['usuario_id'];
            $cupo = $cuposPorUsuario[$userId] ?? null;

            $tieneCupo = $cupo !== null;
            $cupoTotal = $tieneCupo ? (int) $cupo['cupo_total'] : null;
            $cupoVendido = $tieneCupo ? (int) $cupo['cupo_vendido'] : null;
            $cupoDisponible = $tieneCupo ? ($cupoTotal - $cupoVendido) : null;

            $response[] = [
                'id' => $tieneCupo ? (int) $cupo['id'] : null,
                'usuario_id' => $userId,
                'usuario_nombre' => $usuario['usuario_nombre'],
                'evento_id' => $eventoId,
                'entrada_id' => $entradaId,
                'cupo_total' => $cupoTotal,
                'cupo_vendido' => $cupoVendido,
                'cupo_disponible' => $cupoDisponible,
                'tiene_cupo' => $tieneCupo,
            ];
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        $usuarioId = isset($input['usuario_id']) ? (int) $input['usuario_id'] : null;
        $eventoId = isset($input['evento_id']) ? (int) $input['evento_id'] : null;
        $entradaId = isset($input['entrada_id']) ? (int) $input['entrada_id'] : null;
        $cupoTotal = isset($input['cupo_total']) ? (int) $input['cupo_total'] : null;

        if (!$usuarioId || !$eventoId || !$entradaId || $cupoTotal === null) {
            http_response_code(400);
            echo json_encode([
                'error' => 'usuario_id, evento_id, entrada_id y cupo_total son obligatorios.'
            ]);
            exit;
        }

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(<<<SQL
                SELECT id
                FROM promotores_cupos
                WHERE usuario_id = :usuario_id
                  AND evento_id = :evento_id
                  AND entrada_id = :entrada_id
                ORDER BY id ASC
                FOR UPDATE
                LIMIT 1;
            SQL);

            $stmt->execute([
                ':usuario_id' => $usuarioId,
                ':evento_id' => $eventoId,
                ':entrada_id' => $entradaId,
            ]);

            $existingId = $stmt->fetchColumn();

            if ($existingId) {
                $saveStmt = $pdo->prepare(<<<SQL
                    UPDATE promotores_cupos
                    SET cupo_total = :cupo_total
                    WHERE id = :id
                    RETURNING id, usuario_id, evento_id, entrada_id, cupo_total, cupo_vendido;
                SQL);

                $saveStmt->execute([
                    ':cupo_total' => $cupoTotal,
                    ':id' => $existingId,
                ]);
            } else {
                $saveStmt = $pdo->prepare(<<<SQL
                    INSERT INTO promotores_cupos (usuario_id, evento_id, entrada_id, cupo_total)
                    VALUES (:usuario_id, :evento_id, :entrada_id, :cupo_total)
                    RETURNING id, usuario_id, evento_id, entrada_id, cupo_total, cupo_vendido;
                SQL);

                $saveStmt->execute([
                    ':usuario_id' => $usuarioId,
                    ':evento_id' => $eventoId,
                    ':entrada_id' => $entradaId,
                    ':cupo_total' => $cupoTotal,
                ]);
            }

            $row = $saveStmt->fetch();
            $pdo->commit();

            echo json_encode([
                'id' => (int) $row['id'],
                'usuario_id' => (int) $row['usuario_id'],
                'evento_id' => (int) $row['evento_id'],
                'entrada_id' => (int) $row['entrada_id'],
                'cupo_total' => (int) $row['cupo_total'],
                'cupo_vendido' => (int) $row['cupo_vendido'],
                'cupo_disponible' => (int) $row['cupo_total'] - (int) $row['cupo_vendido'],
                'tiene_cupo' => true,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error inesperado: ' . $e->getMessage()]);
}
