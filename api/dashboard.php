<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

date_default_timezone_set('America/Argentina/Cordoba');

function sendCsvExport(string $filename, array $metrics, ?array $monthlySummary, array $pastEvents, ?array $currentNight): void
{
    $rows = [];
    $rows[] = ['Sección', 'Concepto', 'Valor'];
    $rows[] = ['Métricas del mes', 'Eventos del mes', $metrics['eventosMes']];
    $rows[] = ['Métricas del mes', 'Entradas vendidas', $metrics['entradasMes']];
    $rows[] = ['Métricas del mes', 'Recaudación', number_format($metrics['recaudacionMes'], 2, ',', '.')];
    $rows[] = ['Métricas del mes', 'Ocupación promedio', $metrics['ocupacionPromedio'] . '%'];

    if ($monthlySummary) {
        $rows[] = [];
        $rows[] = ['Resumen mensual', 'Mes', $monthlySummary['monthLabel']];
        $rows[] = ['Resumen mensual', 'Total eventos', $monthlySummary['totalEventos']];
        $rows[] = ['Resumen mensual', 'Total entradas', $monthlySummary['totalEntradas']];
        $rows[] = ['Resumen mensual', 'Recaudación', number_format($monthlySummary['recaudacion'], 2, ',', '.')];
        $rows[] = ['Resumen mensual', 'Ocupación promedio', $monthlySummary['ocupacionPromedio'] . '%'];
    }

    if ($currentNight) {
        $rows[] = [];
        $rows[] = ['Evento en curso', 'Nombre', $currentNight['eventName']];
        $rows[] = ['Evento en curso', 'Fecha', $currentNight['fecha']];
        $rows[] = ['Evento en curso', 'Entradas', $currentNight['entradasVendidas']];
        $rows[] = ['Evento en curso', 'Ocupación', $currentNight['ocupacion'] . '%'];
    }

    $rows[] = [];
    $rows[] = ['Eventos del mes', 'Nombre', 'Fecha', 'Entradas', 'Recaudación', 'Ocupación'];
    if (count($pastEvents) === 0) {
        $rows[] = ['Eventos del mes', 'Sin eventos registrados', '', '', '', ''];
    } else {
        foreach ($pastEvents as $event) {
            $rows[] = [
                'Eventos del mes',
                $event['name'],
                substr($event['date'], 0, 10),
                $event['entradasVendidas'],
                number_format($event['recaudacion'], 2, ',', '.'),
                $event['ocupacion'] . '%'
            ];
        }
    }

    $stream = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
        fputcsv($stream, $row, ';');
    }
    rewind($stream);
    $csv = stream_get_contents($stream);
    fclose($stream);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $csv;
    exit;
}

function pdfEscape(string $text): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

function removeAccents(string $text): string
{
    $map = [
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u',
        'Á' => 'A',
        'É' => 'E',
        'Í' => 'I',
        'Ó' => 'O',
        'Ú' => 'U',
        'ñ' => 'n',
        'Ñ' => 'N',
        'ü' => 'u',
        'Ü' => 'U',
    ];

    return strtr($text, $map);
}

function buildSimplePdf(array $lines): string
{
    $content = "BT\n/F1 16 Tf\n";
    $y = 770;
    foreach ($lines as $line) {
        if (function_exists('mb_convert_encoding')) {
            $converted = mb_convert_encoding($line, 'Windows-1252', 'UTF-8');
        } else {
            $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT', $line);
        }
        if ($converted === false) {
            $converted = $line;
        }
        $content .= sprintf("1 0 0 1 72 %.2f Tm\n(%s) Tj\n", $y, pdfEscape($converted));
        $y -= 22;
        if ($y < 72) {
            $y = 750;
        }
    }
    $content .= "ET\n";

    $objects = [];
    $objects[] = "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj";
    $objects[] = "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj";
    $objects[] = "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj";
    $objects[] = "4 0 obj << /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj";
    $objects[] = "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj";

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object . "\n";
    }

    $xrefPosition = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefPosition . "\n%%EOF";

    return $pdf;
}

function sendPdfExport(string $filename, array $metrics, ?array $monthlySummary, array $pastEvents, ?array $currentNight): void
{
    $lines = [];
    $lines[] = 'Dashboard Santas - Resumen de métricas';
    if ($monthlySummary) {
        $lines[] = 'Mes: ' . $monthlySummary['monthLabel'];
    }
    $lines[] = '';
    $lines[] = 'Eventos del mes: ' . $metrics['eventosMes'];
    $lines[] = 'Entradas vendidas: ' . $metrics['entradasMes'];
    $lines[] = 'Recaudación: $' . number_format($metrics['recaudacionMes'], 2, ',', '.');
    $lines[] = 'Ocupación promedio: ' . $metrics['ocupacionPromedio'] . '%';

    if ($currentNight) {
        $lines[] = '';
        $lines[] = 'Evento en curso: ' . $currentNight['eventName'];
        $lines[] = 'Fecha: ' . $currentNight['fecha'];
        $lines[] = 'Entradas: ' . $currentNight['entradasVendidas'];
        $lines[] = 'Ocupación: ' . $currentNight['ocupacion'] . '%';
    }

    $lines[] = '';
    $lines[] = 'Eventos del mes:';
    if (count($pastEvents) === 0) {
        $lines[] = '  • Sin eventos registrados en el período.';
    } else {
        foreach (array_slice($pastEvents, 0, 8) as $event) {
            $lines[] = sprintf(
                '  • %s - %s | Entradas: %d | Recaudación: $%s',
                substr($event['date'], 0, 10),
                $event['name'],
                $event['entradasVendidas'],
                number_format($event['recaudacion'], 2, ',', '.')
            );
        }
        if (count($pastEvents) > 8) {
            $lines[] = '  • ...';
        }
    }

    $sanitizedLines = array_map('removeAccents', $lines);
    $pdf = buildSimplePdf($sanitizedLines);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $pdf;
    exit;
}


$host = "aws-1-us-east-2.pooler.supabase.com";
$port = "5432";
$dbname = "postgres";
$user = "postgres.kxvogvgsgwfvtmidabyp";
$password = "lapicero30!";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password;sslmode=require");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(['error' => 'Método no permitido']);
        exit;
    }

    // --- Parámetros ---
    $dayParam   = $_GET['day']   ?? null;
    $monthParam = $_GET['month'] ?? null;
    $limitUp    = isset($_GET['limitUpcoming']) ? max(1, (int)$_GET['limitUpcoming']) : 3;
    $export     = $_GET['export'] ?? null;

    // --- Rango mensual ---
    if ($monthParam && preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
        [$y, $m] = explode('-', $monthParam);
        $monthStart = "$y-$m-01";
        $monthEnd   = date('Y-m-d', strtotime("$monthStart +1 month"));
    } else {
        $monthStart = date('Y-m-01');
        $monthEnd   = date('Y-m-d', strtotime("$monthStart +1 month"));
    }

    // === 1. Métricas del mes ===
    // Asegúrate de que todos los eventos del mes, ya cerrados o no, sean considerados.
    $metricsSql = "
    WITH event_stats AS (
        SELECT
            e.id,
            e.fecha,
            e.capacidad,
            COALESCE(SUM(ve.cantidad), 0) AS entradas,
            COALESCE(SUM(ve.total), 0) AS total
        FROM eventos e
        LEFT JOIN ventas_entradas ve ON e.id = ve.evento_id
        WHERE e.fecha >= :mstart::date AND e.fecha < :mend::date  -- Considera todos los eventos del mes
        GROUP BY e.id, e.fecha, e.capacidad
    ),
    daily_stats AS (
        SELECT
            DATE(fecha) AS dia,
            CASE
                WHEN SUM(capacidad) > 0 THEN (SUM(entradas) * 100.0) / SUM(capacidad)
                ELSE NULL
            END AS ocupacion
        FROM event_stats
        GROUP BY DATE(fecha)
    )
    SELECT
        COUNT(*) AS eventos_mes,
        COALESCE(SUM(entradas), 0) AS entradas_mes,
        COALESCE(SUM(total), 0) AS recaudacion_mes,
        (SELECT ROUND(AVG(ocupacion)) FROM daily_stats) AS ocupacion_promedio
    FROM event_stats
";


    $st = $pdo->prepare($metricsSql);
    $st->execute([':mstart' => $monthStart, ':mend' => $monthEnd]);
    $m = $st->fetch() ?: ['eventos_mes' => 0, 'entradas_mes' => 0, 'recaudacion_mes' => 0, 'ocupacion_promedio' => 0];

    $metrics = [
        'eventosMes'        => (int)$m['eventos_mes'],
        'entradasMes'       => (int)$m['entradas_mes'],
        'recaudacionMes'    => (float)$m['recaudacion_mes'],
        'ocupacionPromedio' => $m['ocupacion_promedio'] !== null ? (int)$m['ocupacion_promedio'] : 0,
    ];

    // === 2. Noche en curso ===
    $currentSql = "
        SELECT e.id, e.nombre AS event_name,
               TO_CHAR(e.fecha, 'DD Mon YYYY') AS fecha_txt,
               COALESCE(SUM(ve.cantidad),0) AS entradas_vendidas,
               COALESCE(SUM(ve.total),0) AS recaudacion,
               ROUND((COALESCE(SUM(ve.cantidad),0)*100.0)/NULLIF(e.capacidad,0)) AS ocupacion
        FROM eventos e
        LEFT JOIN ventas_entradas ve ON ve.evento_id = e.id
        WHERE DATE(e.fecha) = CURRENT_DATE
        GROUP BY e.id, e.nombre, e.fecha, e.capacidad
        ORDER BY e.fecha DESC
        LIMIT 1
    ";
    $currentNightRow = $pdo->query($currentSql)->fetch();
    $currentNight = $currentNightRow ? [
        'eventName'        => $currentNightRow['event_name'],
        'fecha'            => $currentNightRow['fecha_txt'],
        'horaInicio'       => '23:00',
        'horaFinEstimada'  => '05:30',
        'entradasVendidas' => (int)$currentNightRow['entradas_vendidas'],
        'recaudacion'      => (float)$currentNightRow['recaudacion'],
        'ocupacion'        => (int)($currentNightRow['ocupacion'] ?? 0),
        'consumoPromedio'  => 0,
        'barrasActivas'    => 0,
        'mesasReservadas'  => 0
    ] : null;

    // === 3. Próximos eventos ===
    $upcomingSql = "
    SELECT 
        e.id,
        e.nombre AS name,  -- 👈 cambiado a name para mantener consistencia
        TO_CHAR(e.fecha, 'YYYY-MM-DD HH24:MI:SS') AS date_text,
        COALESCE(SUM(ve.total),0) AS recaudacion,
        ROUND((COALESCE(SUM(ve.cantidad),0)*100.0)/NULLIF(e.capacidad,0)) AS ocupacion
    FROM eventos e
    LEFT JOIN ventas_entradas ve ON ve.evento_id = e.id
    WHERE e.fecha > NOW() AND e.activo = TRUE
    GROUP BY e.id, e.nombre, e.fecha, e.capacidad
    ORDER BY e.fecha ASC
    LIMIT :limitUp
";
    $up = $pdo->prepare($upcomingSql);
    $up->bindValue(':limitUp', $limitUp, PDO::PARAM_INT);
    $up->execute();

    $upcomingEvents = array_map(function ($r) {
        return [
            'id'          => (string)$r['id'],
            'name'        => $r['name'],        // ✅ ahora coincide con pastEvents
            'date'        => str_replace(' ', 'T', $r['date_text']),
            'recaudacion' => (float)$r['recaudacion'],
            'ocupacion'   => (int)($r['ocupacion'] ?? 0)
        ];
    }, $up->fetchAll() ?: []);



    // === 4. Eventos pasados ===
    $params = [':mstart' => $monthStart, ':mend' => $monthEnd];
    $whereParts = [
        "DATE(e.fecha) >= :mstart::date",
        "DATE(e.fecha) < :mend::date",
        "e.fecha < NOW()"
    ];
    if ($dayParam && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayParam)) {
        $whereParts = ["DATE(e.fecha) = :day::date"];
        $params = [':day' => $dayParam];
    }

    $where = implode(' AND ', $whereParts);

    $pastSql = "
        SELECT e.id,
               e.nombre AS name,
               TO_CHAR(e.fecha, 'YYYY-MM-DD HH24:MI:SS') AS date_text,
               COALESCE(ce.total_vendido, COALESCE(SUM(ve.cantidad),0)) AS entradas_vendidas,
               COALESCE(ce.total_monto, COALESCE(SUM(ve.total),0)) AS recaudacion,
               COALESCE(
                    ce.porcentaje,
                    ROUND((COALESCE(SUM(ve.cantidad),0)*100.0)/NULLIF(e.capacidad,0))
               ) AS ocupacion
        FROM eventos e
        LEFT JOIN ventas_entradas ve ON ve.evento_id = e.id
        LEFT JOIN cierres_eventos ce ON ce.evento_id = e.id
        WHERE $where
        GROUP BY e.id, e.nombre, e.fecha, e.capacidad, ce.total_vendido, ce.total_monto, ce.porcentaje
        ORDER BY e.fecha DESC
    ";
    $pst = $pdo->prepare($pastSql);
    $pst->execute($params);
    $pastEvents = array_map(fn($r) => [
        'id' => (string)$r['id'],
        'name' => $r['name'],
        'date' => str_replace(' ', 'T', $r['date_text']),
        'entradasVendidas' => (int)$r['entradas_vendidas'],
        'recaudacion' => (float)$r['recaudacion'],
        'ocupacion' => (int)($r['ocupacion'] ?? 0),
        'consumoPromedio' => 0,
        'barrasActivas' => 0,
        'mesasReservadas' => 0
    ], $pst->fetchAll() ?: []);

    $calendarStmt = $pdo->prepare("
        SELECT e.id,
               e.nombre AS name,
               TO_CHAR(e.fecha, 'YYYY-MM-DD HH24:MI:SS') AS date_text,
               COALESCE(ce.total_vendido, COALESCE(SUM(ve.cantidad),0)) AS entradas_vendidas,
               COALESCE(ce.total_monto, COALESCE(SUM(ve.total),0)) AS recaudacion,
               COALESCE(
                    ce.porcentaje,
                    ROUND((COALESCE(SUM(ve.cantidad),0)*100.0)/NULLIF(e.capacidad,0))
               ) AS ocupacion,
               e.activo AS activo,
               CASE WHEN ce.evento_id IS NOT NULL THEN TRUE ELSE FALSE END AS cerrado
        FROM eventos e
        LEFT JOIN ventas_entradas ve ON ve.evento_id = e.id
        LEFT JOIN cierres_eventos ce ON ce.evento_id = e.id
        WHERE DATE(e.fecha) >= :mstart::date AND DATE(e.fecha) < :mend::date
        GROUP BY e.id, e.nombre, e.fecha, e.capacidad, ce.total_vendido, ce.total_monto, ce.porcentaje, e.activo, ce.evento_id
        ORDER BY e.fecha ASC
    ");
    $calendarStmt->execute([':mstart' => $monthStart, ':mend' => $monthEnd]);
    $calendarEvents = array_map(fn($r) => [
        'id' => (string)$r['id'],
        'name' => $r['name'],
        'date' => str_replace(' ', 'T', $r['date_text']),
        'entradasVendidas' => (int)$r['entradas_vendidas'],
        'recaudacion' => (float)$r['recaudacion'],
        'ocupacion' => (int)($r['ocupacion'] ?? 0),
        'activo' => filter_var($r['activo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
        'cerrado' => filter_var($r['cerrado'], FILTER_VALIDATE_BOOLEAN)
    ], $calendarStmt->fetchAll() ?: []);

    // === 5. Resumen mensual ===
    $monthlySql = "
    WITH event_stats AS (
            SELECT
                e.id,
                e.fecha,
                e.capacidad,
                COALESCE(SUM(ve.cantidad), 0) AS entradas,
                COALESCE(SUM(ve.total), 0) AS total
            FROM eventos e
            LEFT JOIN ventas_entradas ve ON e.id = ve.evento_id
            WHERE e.fecha >= :mstart::date AND e.fecha < :mend::date
            GROUP BY e.id, e.fecha, e.capacidad
        ),
        daily_stats AS (
            SELECT
                DATE(fecha) AS dia,
                CASE
                    WHEN SUM(capacidad) > 0 THEN (SUM(entradas) * 100.0) / SUM(capacidad)
                    ELSE NULL
                END AS ocupacion
            FROM event_stats
            GROUP BY DATE(fecha)
        )
        SELECT TO_CHAR(:mstart::date, 'TMMonth YYYY') AS month_label,
               COUNT(*) AS total_eventos,
               COALESCE(SUM(entradas),0) AS total_entradas,
               COALESCE(SUM(total),0) AS recaudacion,
               (SELECT ROUND(AVG(ocupacion)) FROM daily_stats) AS ocupacion_promedio
        FROM event_stats
    ";
    $ms = $pdo->prepare($monthlySql);
    $ms->execute([':mstart' => $monthStart, ':mend' => $monthEnd]);
    $mr = $ms->fetch();
    $monthlySummary = $mr ? [
        'monthLabel' => trim($mr['month_label']),
        'totalEventos' => (int)$mr['total_eventos'],
        'totalEntradas' => (int)$mr['total_entradas'],
        'recaudacion' => (float)$mr['recaudacion'],
        'ocupacionPromedio' => (int)($mr['ocupacion_promedio'] ?? 0),
        'mejorNoche' => null
    ] : null;

    // === 6. Ventas semanales ===
    $salesStmt = $pdo->query("
        SELECT TO_CHAR(DATE(e.fecha),'Dy') AS name, COALESCE(SUM(ve.total),0) AS ventas
        FROM eventos e
        LEFT JOIN ventas_entradas ve ON e.id = ve.evento_id
        WHERE e.fecha >= NOW() - INTERVAL '7 day'
        GROUP BY 1 ORDER BY MIN(e.fecha)
    ");
    $salesData = array_map(fn($r) => ['name' => $r['name'], 'ventas' => (float)$r['ventas']], $salesStmt->fetchAll() ?: []);

    // === 7. Asistencia semanal ===
    $attendanceStmt = $pdo->query("
        SELECT TO_CHAR(DATE(e.fecha),'Dy') AS name, COALESCE(SUM(ve.cantidad),0) AS asistencia
        FROM eventos e
        LEFT JOIN ventas_entradas ve ON e.id = ve.evento_id
        WHERE e.fecha >= NOW() - INTERVAL '7 day'
        GROUP BY 1 ORDER BY MIN(e.fecha)
    ");
    $attendanceData = array_map(fn($r) => ['name' => $r['name'], 'asistencia' => (int)$r['asistencia']], $attendanceStmt->fetchAll() ?: []);

    // === 8. Actividad reciente ===
    $recentStmt = $pdo->query("
        SELECT e.nombre AS type, TO_CHAR(e.fecha,'DD Mon HH24:MI') AS time
        FROM eventos e ORDER BY e.fecha DESC LIMIT 5
    ");
    $recentActivity = [];
    foreach ($recentStmt->fetchAll() as $i => $r) {
        $recentActivity[] = [
            'id' => $i + 1,
            'type' => $r['type'],
            'description' => 'Evento registrado en el sistema.',
            'time' => $r['time'],
            'color' => 'text-primary'
        ];
    }

    // === 9. Distribución por evento ===
    $categoryStmt = $pdo->query("
        SELECT e.nombre AS name,
               ROUND((COALESCE(SUM(ve.total),0)/NULLIF((SELECT SUM(total) FROM ventas_entradas),0))*100,2) AS value
        FROM eventos e
        LEFT JOIN ventas_entradas ve ON e.id = ve.evento_id
        GROUP BY e.nombre
        ORDER BY value DESC
        LIMIT 5
    ");
    $colors = ['#7C3AED', '#3B82F6', '#22C55E', '#EAB308', '#EF4444'];
    $categoryData = [];
    foreach ($categoryStmt->fetchAll() as $i => $r) {
        $categoryData[] = [
            'name' => $r['name'],
            'value' => (float)$r['value'],
            'color' => $colors[$i % count($colors)]
        ];
    }

    $response = [
        'metrics' => $metrics,
        'currentNight' => $currentNight,
        'upcomingEvents' => $upcomingEvents,
        'pastEvents' => $pastEvents,
        'calendarEvents' => $calendarEvents,
        'monthlySummary' => $monthlySummary,
        'salesData' => $salesData,
        'attendanceData' => $attendanceData,
        'categoryData' => $categoryData,
        'recentActivity' => $recentActivity
    ];

    if ($export === 'csv') {
        sendCsvExport(
            'dashboard-metricas-' . date('Ymd_His') . '.csv',
            $metrics,
            $monthlySummary,
            $pastEvents,
            $currentNight
        );
    }

    if ($export === 'pdf') {
        sendPdfExport(
            'dashboard-metricas-' . date('Ymd_His') . '.pdf',
            $metrics,
            $monthlySummary,
            $pastEvents,
            $currentNight
        );
    }

    // === Salida final ===
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(['error' => $e->getMessage()]);
}
