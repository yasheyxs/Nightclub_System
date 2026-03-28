<?php

declare(strict_types=1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

date_default_timezone_set('America/Argentina/Cordoba');

// ======================================
// CONFIG
// ======================================
$host = "aws-1-us-east-2.pooler.supabase.com";
$port = "5432";
$dbname = "postgres";
$user = "postgres.kxvogvgsgwfvtmidabyp";
$password = "lapicero30!";

// ======================================
// HELPERS
// ======================================
function jsonResponse(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        jsonResponse(400, ['ok' => false, 'error' => 'Solicitud inválida.']);
    }

    return $data;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(405, ['ok' => false, 'error' => 'Método no permitido.']);
    }

    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password;sslmode=require"
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $input = getJsonInput();

    if (!isset($input['evento_id'], $input['entrada_id'], $input['cantidad'])) {
        jsonResponse(400, [
            'ok' => false,
            'error' => 'Los campos evento_id, entrada_id y cantidad son obligatorios.'
        ]);
    }

    $eventoId = (int)$input['evento_id'];
    $entradaId = (int)$input['entrada_id'];
    $cantidad = (int)$input['cantidad'];
    $motivo = isset($input['motivo']) ? trim((string)$input['motivo']) : 'Ajuste manual desde panel';
    $usuarioId = isset($input['usuario_id']) ? (int)$input['usuario_id'] : null;

    if ($eventoId <= 0 || $entradaId <= 0) {
        jsonResponse(400, [
            'ok' => false,
            'error' => 'evento_id y entrada_id deben ser mayores a 0.'
        ]);
    }

    if ($cantidad <= 0) {
        jsonResponse(400, [
            'ok' => false,
            'error' => 'La cantidad a anular debe ser mayor a 0.'
        ]);
    }

    $eventoStmt = $pdo->prepare("
        SELECT id, nombre
        FROM eventos
        WHERE id = :id
        LIMIT 1
    ");
    $eventoStmt->execute([':id' => $eventoId]);
    $evento = $eventoStmt->fetch();

    if (!$evento) {
        jsonResponse(404, [
            'ok' => false,
            'error' => 'Evento no encontrado.'
        ]);
    }

    $entradaStmt = $pdo->prepare("
        SELECT id, nombre
        FROM entradas
        WHERE id = :id
        LIMIT 1
    ");
    $entradaStmt->execute([':id' => $entradaId]);
    $entrada = $entradaStmt->fetch();

    if (!$entrada) {
        jsonResponse(404, [
            'ok' => false,
            'error' => 'Entrada no encontrada.'
        ]);
    }

    $disponiblesStmt = $pdo->prepare("
        SELECT COUNT(*) AS disponibles
        FROM ventas_entradas
        WHERE evento_id = :evento_id
          AND entrada_id = :entrada_id
          AND estado = 'comprada'
    ");
    $disponiblesStmt->execute([
        ':evento_id' => $eventoId,
        ':entrada_id' => $entradaId
    ]);

    $disponibles = (int)($disponiblesStmt->fetch()['disponibles'] ?? 0);

    if ($disponibles <= 0) {
        jsonResponse(400, [
            'ok' => false,
            'error' => 'No hay entradas en estado comprada para anular.'
        ]);
    }

    if ($cantidad > $disponibles) {
        jsonResponse(400, [
            'ok' => false,
            'error' => 'No podés anular más entradas de las disponibles.',
            'disponibles' => $disponibles
        ]);
    }

    $pdo->beginTransaction();

    $anularStmt = $pdo->prepare("
        WITH candidatas AS (
            SELECT id
            FROM ventas_entradas
            WHERE evento_id = :evento_id
              AND entrada_id = :entrada_id
              AND estado = 'comprada'
            ORDER BY fecha_venta DESC, id DESC
            LIMIT :cantidad
            FOR UPDATE
        )
        UPDATE ventas_entradas v
        SET
            estado = 'anulada',
            observaciones = CASE
                WHEN COALESCE(v.observaciones, '') = '' THEN :observaciones
                ELSE v.observaciones || ' | ' || :observaciones
            END
        FROM candidatas c
        WHERE v.id = c.id
        RETURNING
            v.id,
            v.evento_id,
            v.entrada_id,
            v.estado,
            v.fecha_venta,
            v.qr_codigo,
            v.nombre,
            v.dni,
            v.observaciones
    ");

    $observaciones = $usuarioId !== null
        ? $motivo . ' | usuario_id=' . $usuarioId
        : $motivo;

    $anularStmt->bindValue(':evento_id', $eventoId, PDO::PARAM_INT);
    $anularStmt->bindValue(':entrada_id', $entradaId, PDO::PARAM_INT);
    $anularStmt->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
    $anularStmt->bindValue(':observaciones', $observaciones, PDO::PARAM_STR);
    $anularStmt->execute();

    $anuladas = $anularStmt->fetchAll();

    if (count($anuladas) !== $cantidad) {
        $pdo->rollBack();
        jsonResponse(409, [
            'ok' => false,
            'error' => 'No se pudieron anular todas las entradas solicitadas. Reintentá.'
        ]);
    }

    $pdo->commit();

    jsonResponse(200, [
        'ok' => true,
        'mensaje' => count($anuladas) . ' ' . (count($anuladas) === 1 ? 'entrada anulada' : 'entradas anuladas') . ' correctamente.',
        'resumen' => [
            'evento_id' => $eventoId,
            'evento_nombre' => $evento['nombre'],
            'entrada_id' => $entradaId,
            'entrada_nombre' => $entrada['nombre'],
            'cantidad_anulada' => count($anuladas)
        ],
        'tickets' => array_map(function ($row) {
            return [
                'id' => (int)$row['id'],
                'evento_id' => $row['evento_id'] !== null ? (int)$row['evento_id'] : null,
                'entrada_id' => $row['entrada_id'] !== null ? (int)$row['entrada_id'] : null,
                'estado' => $row['estado'],
                'fecha_venta' => $row['fecha_venta'],
                'qr_codigo' => $row['qr_codigo'],
                'nombre' => $row['nombre'],
                'dni' => $row['dni'],
                'observaciones' => $row['observaciones']
            ];
        }, $anuladas)
    ]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(500, [
        'ok' => false,
        'error' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(500, [
        'ok' => false,
        'error' => 'Error inesperado: ' . $e->getMessage()
    ]);
}
