<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

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

function sendJson(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function getSpanishMonthLabel(string $dateYmd): string
{
    $months = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];

    $timestamp = strtotime($dateYmd);
    $month = (int)date('n', $timestamp);
    $year = date('Y', $timestamp);

    return ($months[$month] ?? '') . ' ' . $year;
}

function sendCsvExport(
    string $filename,
    array $metrics,
    ?array $monthlySummary,
    array $pastEvents,
    ?array $currentNight,
    array $upcomingEvents = []
): void {
    $rows = [];

    $rows[] = ['==== RESUMEN GENERAL ===='];
    $rows[] = ['Eventos del mes', $metrics['eventosMes']];
    $rows[] = ['Entradas vendidas', $metrics['entradasMes']];
    $rows[] = ['Entradas escaneadas', $metrics['entradasEscaneadas']];
    $rows[] = ['Recaudación', '$' . number_format((float)$metrics['recaudacionMes'], 2, ',', '.')];
    $rows[] = ['Ocupación promedio', $metrics['ocupacionPromedio'] . '%'];

    if ($monthlySummary !== null) {
        $rows[] = [];
        $rows[] = ['==== RESUMEN MENSUAL ===='];
        $rows[] = ['Mes', $monthlySummary['monthLabel']];
        $rows[] = ['Total eventos', $monthlySummary['totalEventos']];
        $rows[] = ['Total entradas', $monthlySummary['totalEntradas']];
        $rows[] = ['Recaudación', '$' . number_format((float)$monthlySummary['recaudacion'], 2, ',', '.')];
        $rows[] = ['Ocupación promedio', $monthlySummary['ocupacionPromedio'] . '%'];
    }

    if ($currentNight !== null) {
        $rows[] = [];
        $rows[] = ['==== EVENTO EN CURSO ===='];
        $rows[] = ['Nombre', $currentNight['eventName']];
        $rows[] = ['Fecha', $currentNight['fecha']];
        $rows[] = ['Entradas', $currentNight['entradasVendidas']];
        $rows[] = ['Recaudación', '$' . number_format((float)$currentNight['recaudacion'], 2, ',', '.')];
        $rows[] = ['Ocupación', $currentNight['ocupacion'] . '%'];
    }

    $rows[] = [];
    $rows[] = ['==== EVENTOS DEL MES ===='];
    $rows[] = ['Nombre', 'Fecha', 'Entradas', 'Recaudación', 'Ocupación'];

    foreach ($pastEvents as $event) {
        $rows[] = [
            $event['name'],
            substr((string)$event['date'], 0, 10),
            $event['entradasVendidas'],
            '$' . number_format((float)$event['recaudacion'], 2, ',', '.'),
            $event['ocupacion'] . '%',
        ];
    }

    if (!empty($upcomingEvents)) {
        $rows[] = [];
        $rows[] = ['==== PRÓXIMOS EVENTOS ===='];
        $rows[] = ['Nombre', 'Fecha', 'Recaudación', 'Ocupación'];

        foreach ($upcomingEvents as $event) {
            $rows[] = [
                $event['name'],
                substr((string)$event['date'], 0, 10),
                '$' . number_format((float)$event['recaudacion'], 2, ',', '.'),
                $event['ocupacion'] . '%',
            ];
        }
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $stream = fopen('php://output', 'w');
    foreach ($rows as $row) {
        fputcsv($stream, $row, ';');
    }
    fclose($stream);
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

        $content .= sprintf(
            "1 0 0 1 72 %.2f Tm\n(%s) Tj\n",
            $y,
            pdfEscape($converted)
        );

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

function sendPdfExport(
    string $filename,
    array $metrics,
    ?array $monthlySummary,
    array $pastEvents,
    ?array $currentNight
): void {
    $lines = [];

    $lines[] = '===== DASHBOARD SANTAS =====';
    $lines[] = '';

    $lines[] = '--- RESUMEN GENERAL ---';
    $lines[] = 'Eventos: ' . $metrics['eventosMes'];
    $lines[] = 'Entradas: ' . $metrics['entradasMes'];
    $lines[] = 'Escaneadas: ' . $metrics['entradasEscaneadas'];
    $lines[] = 'Recaudacion: $' . number_format((float)$metrics['recaudacionMes'], 2, ',', '.');
    $lines[] = 'Ocupacion: ' . $metrics['ocupacionPromedio'] . '%';

    if ($monthlySummary !== null) {
        $lines[] = '';
        $lines[] = '--- RESUMEN MENSUAL ---';
        $lines[] = 'Mes: ' . $monthlySummary['monthLabel'];
        $lines[] = 'Eventos: ' . $monthlySummary['totalEventos'];
        $lines[] = 'Entradas: ' . $monthlySummary['totalEntradas'];
        $lines[] = 'Recaudacion: $' . number_format((float)$monthlySummary['recaudacion'], 2, ',', '.');
    }

    if ($currentNight !== null) {
        $lines[] = '';
        $lines[] = '--- EVENTO EN CURSO ---';
        $lines[] = $currentNight['eventName'];
        $lines[] = 'Entradas: ' . $currentNight['entradasVendidas'];
        $lines[] = 'Ocupacion: ' . $currentNight['ocupacion'] . '%';
    }

    $lines[] = '';
    $lines[] = '--- EVENTOS DEL MES ---';

    foreach ($pastEvents as $event) {
        $lines[] = sprintf(
            '%s | %s | %d entradas | $%s',
            substr((string)$event['date'], 0, 10),
            $event['name'],
            (int)$event['entradasVendidas'],
            number_format((float)$event['recaudacion'], 2, ',', '.')
        );
    }

    $pdf = buildSimplePdf(array_map('removeAccents', $lines));

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $pdf;
    exit;
}

try {
    $globalStart = microtime(true);

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJson(405, ['error' => 'Método no permitido']);
    }

    $timer = microtime(true);
    $pdo = getPdo();
    logTime('PDO connect/reuse', $timer);

    $dayParam = $_GET['day'] ?? null;
    $monthParam = $_GET['month'] ?? null;
    $limitUp = isset($_GET['limitUpcoming']) ? max(1, (int)$_GET['limitUpcoming']) : 3;
    $export = $_GET['export'] ?? null;

    if ($monthParam !== null && preg_match('/^\d{4}-\d{2}$/', $monthParam) === 1) {
        [$year, $month] = explode('-', $monthParam);
        $monthStartDate = sprintf('%04d-%02d-01', (int)$year, (int)$month);
    } else {
        $monthStartDate = date('Y-m-01');
    }

    $monthStart = $monthStartDate . ' 00:00:00';
    $monthEnd = date('Y-m-d H:i:s', strtotime($monthStart . ' +1 month'));

    $todayStart = date('Y-m-d 00:00:00');
    $tomorrowStart = date('Y-m-d 00:00:00', strtotime('+1 day'));
    $nowString = date('Y-m-d H:i:s');

    $dayStart = null;
    $dayEnd = null;

    if ($dayParam !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayParam) === 1) {
        $dayStart = $dayParam . ' 00:00:00';
        $dayEnd = date('Y-m-d H:i:s', strtotime($dayStart . ' +1 day'));
    }

    $timer = microtime(true);
    $baseMonthlySql = "
        SELECT
            e.id,
            e.nombre AS name,
            TO_CHAR(e.fecha, 'YYYY-MM-DD HH24:MI:SS') AS date_text,
            e.fecha,
            e.capacidad,
            e.activo,
            COALESCE(ce.total_vendido, COALESCE(SUM(ve.cantidad), 0)) AS entradas_vendidas,
            COALESCE(ce.total_monto, COALESCE(SUM(ve.total), 0)) AS recaudacion,
            COALESCE(
                ce.porcentaje,
                CASE
                    WHEN e.capacidad > 0
                        THEN ROUND((COALESCE(SUM(ve.cantidad), 0) * 100.0) / e.capacidad)
                    ELSE 0
                END
            ) AS ocupacion,
            CASE
                WHEN ce.evento_id IS NOT NULL THEN TRUE
                ELSE FALSE
            END AS cerrado
        FROM eventos e
        LEFT JOIN ventas_entradas ve
            ON ve.evento_id = e.id
            AND ve.estado IN ('comprada', 'usada')
        LEFT JOIN cierres_eventos ce
            ON ce.evento_id = e.id
        WHERE e.fecha >= :mstart
          AND e.fecha < :mend
        GROUP BY
            e.id,
            e.nombre,
            e.fecha,
            e.capacidad,
            e.activo,
            ce.total_vendido,
            ce.total_monto,
            ce.porcentaje,
            ce.evento_id
        ORDER BY e.fecha ASC
    ";

    $baseMonthlyStmt = $pdo->prepare($baseMonthlySql);
    $baseMonthlyStmt->execute([
        ':mstart' => $monthStart,
        ':mend' => $monthEnd,
    ]);
    $monthlyRows = $baseMonthlyStmt->fetchAll() ?: [];
    logTime('base mensual query', $timer);

    $timer = microtime(true);
    $calendarEvents = [];
    $pastEvents = [];

    $eventosMes = 0;
    $entradasMes = 0;
    $recaudacionMes = 0.0;
    $ocupacionSum = 0.0;
    $ocupacionCount = 0;

    foreach ($monthlyRows as $row) {
        $eventDate = (string)$row['fecha'];

        $calendarEvents[] = [
            'id' => (string)$row['id'],
            'name' => (string)$row['name'],
            'date' => str_replace(' ', 'T', (string)$row['date_text']),
            'entradasVendidas' => (int)$row['entradas_vendidas'],
            'recaudacion' => (float)$row['recaudacion'],
            'ocupacion' => (int)round((float)($row['ocupacion'] ?? 0)),
            'activo' => (bool)$row['activo'],
            'cerrado' => (bool)$row['cerrado'],
        ];

        $eventosMes++;
        $entradasMes += (int)$row['entradas_vendidas'];
        $recaudacionMes += (float)$row['recaudacion'];

        if ($row['ocupacion'] !== null) {
            $ocupacionSum += (float)$row['ocupacion'];
            $ocupacionCount++;
        }

        $isPastOrClosed = ($eventDate < $nowString) || ((bool)$row['cerrado'] === true);

        if ($dayStart !== null && $dayEnd !== null) {
            if ($eventDate >= $dayStart && $eventDate < $dayEnd) {
                $pastEvents[] = [
                    'id' => (string)$row['id'],
                    'name' => (string)$row['name'],
                    'date' => str_replace(' ', 'T', (string)$row['date_text']),
                    'entradasVendidas' => (int)$row['entradas_vendidas'],
                    'recaudacion' => (float)$row['recaudacion'],
                    'ocupacion' => (int)round((float)($row['ocupacion'] ?? 0)),
                    'consumoPromedio' => 0,
                    'barrasActivas' => 0,
                    'mesasReservadas' => 0,
                ];
            }
        } elseif ($isPastOrClosed) {
            $pastEvents[] = [
                'id' => (string)$row['id'],
                'name' => (string)$row['name'],
                'date' => str_replace(' ', 'T', (string)$row['date_text']),
                'entradasVendidas' => (int)$row['entradas_vendidas'],
                'recaudacion' => (float)$row['recaudacion'],
                'ocupacion' => (int)round((float)($row['ocupacion'] ?? 0)),
                'consumoPromedio' => 0,
                'barrasActivas' => 0,
                'mesasReservadas' => 0,
            ];
        }
    }

    usort(
        $pastEvents,
        static fn(array $a, array $b): int => strcmp((string)$b['date'], (string)$a['date'])
    );

    $metrics = [
        'eventosMes' => $eventosMes,
        'entradasMes' => $entradasMes,
        'entradasEscaneadas' => 0,
        'recaudacionMes' => $recaudacionMes,
        'ocupacionPromedio' => $ocupacionCount > 0 ? (int)round($ocupacionSum / $ocupacionCount) : 0,
    ];

    $monthlySummary = [
        'monthLabel' => getSpanishMonthLabel($monthStartDate),
        'totalEventos' => $metrics['eventosMes'],
        'totalEntradas' => $metrics['entradasMes'],
        'recaudacion' => $metrics['recaudacionMes'],
        'ocupacionPromedio' => $metrics['ocupacionPromedio'],
        'mejorNoche' => null,
    ];
    logTime('base mensual armado PHP', $timer);

    $timer = microtime(true);
    $scannedSql = "
        SELECT COUNT(a.id) AS entradas_escaneadas
        FROM accesos_qr a
        INNER JOIN ventas_entradas v
            ON v.id = a.venta_entrada_id
        INNER JOIN eventos e
            ON e.id = v.evento_id
        WHERE a.resultado = 'valido'
          AND e.fecha >= :mstart
          AND e.fecha < :mend
    ";
    $scannedStmt = $pdo->prepare($scannedSql);
    $scannedStmt->execute([
        ':mstart' => $monthStart,
        ':mend' => $monthEnd,
    ]);
    $scannedRow = $scannedStmt->fetch();
    $metrics['entradasEscaneadas'] = (int)($scannedRow['entradas_escaneadas'] ?? 0);
    logTime('escaneadas', $timer);

    $timer = microtime(true);
    $currentSql = "
        SELECT
            e.id,
            e.nombre AS event_name,
            TO_CHAR(e.fecha, 'DD Mon YYYY') AS fecha_txt,
            COALESCE(SUM(ve.cantidad), 0) AS entradas_vendidas,
            COALESCE(SUM(ve.total), 0) AS recaudacion,
            CASE
                WHEN e.capacidad > 0
                    THEN ROUND((COALESCE(SUM(ve.cantidad), 0) * 100.0) / e.capacidad)
                ELSE 0
            END AS ocupacion
        FROM eventos e
        LEFT JOIN ventas_entradas ve
            ON ve.evento_id = e.id
            AND ve.estado IN ('comprada', 'usada')
        WHERE e.fecha >= :todayStart
          AND e.fecha < :tomorrowStart
        GROUP BY e.id, e.nombre, e.fecha, e.capacidad
        ORDER BY e.fecha DESC
        LIMIT 1
    ";
    $currentStmt = $pdo->prepare($currentSql);
    $currentStmt->execute([
        ':todayStart' => $todayStart,
        ':tomorrowStart' => $tomorrowStart,
    ]);
    $currentNightRow = $currentStmt->fetch();

    $currentNight = $currentNightRow ? [
        'eventName' => (string)$currentNightRow['event_name'],
        'fecha' => (string)$currentNightRow['fecha_txt'],
        'horaInicio' => '23:00',
        'horaFinEstimada' => '05:30',
        'entradasVendidas' => (int)$currentNightRow['entradas_vendidas'],
        'recaudacion' => (float)$currentNightRow['recaudacion'],
        'ocupacion' => (int)round((float)($currentNightRow['ocupacion'] ?? 0)),
        'consumoPromedio' => 0,
        'barrasActivas' => 0,
        'mesasReservadas' => 0,
    ] : null;
    logTime('currentNight', $timer);

    $timer = microtime(true);
    $upcomingSql = "
        SELECT
            e.id,
            e.nombre AS name,
            TO_CHAR(e.fecha, 'YYYY-MM-DD HH24:MI:SS') AS date_text,
            COALESCE(SUM(ve.total), 0) AS recaudacion,
            CASE
                WHEN e.capacidad > 0
                    THEN ROUND((COALESCE(SUM(ve.cantidad), 0) * 100.0) / e.capacidad)
                ELSE 0
            END AS ocupacion
        FROM eventos e
        LEFT JOIN ventas_entradas ve
            ON ve.evento_id = e.id
            AND ve.estado IN ('comprada', 'usada')
        WHERE e.fecha > NOW()
          AND e.activo = TRUE
        GROUP BY e.id, e.nombre, e.fecha, e.capacidad
        ORDER BY e.fecha ASC
        LIMIT :limitUp
    ";
    $upcomingStmt = $pdo->prepare($upcomingSql);
    $upcomingStmt->bindValue(':limitUp', $limitUp, PDO::PARAM_INT);
    $upcomingStmt->execute();

    $upcomingEvents = array_map(
        static fn(array $row): array => [
            'id' => (string)$row['id'],
            'name' => (string)$row['name'],
            'date' => str_replace(' ', 'T', (string)$row['date_text']),
            'recaudacion' => (float)$row['recaudacion'],
            'ocupacion' => (int)round((float)($row['ocupacion'] ?? 0)),
        ],
        $upcomingStmt->fetchAll() ?: []
    );
    logTime('upcoming', $timer);

    $timer = microtime(true);
    $weeklySql = "
        SELECT
            TO_CHAR(DATE_TRUNC('day', e.fecha), 'Dy') AS name,
            DATE_TRUNC('day', e.fecha) AS dia_orden,
            COALESCE(SUM(ve.total), 0) AS ventas,
            COALESCE(SUM(ve.cantidad), 0) AS asistencia
        FROM eventos e
        LEFT JOIN ventas_entradas ve
            ON ve.evento_id = e.id
            AND ve.estado IN ('comprada', 'usada')
        WHERE e.fecha >= NOW() - INTERVAL '7 day'
        GROUP BY DATE_TRUNC('day', e.fecha)
        ORDER BY dia_orden ASC
    ";
    $weeklyStmt = $pdo->query($weeklySql);
    $weeklyRows = $weeklyStmt->fetchAll() ?: [];

    $salesData = [];
    $attendanceData = [];

    foreach ($weeklyRows as $row) {
        $name = trim((string)$row['name']);

        $salesData[] = [
            'name' => $name,
            'ventas' => (float)$row['ventas'],
        ];

        $attendanceData[] = [
            'name' => $name,
            'asistencia' => (int)$row['asistencia'],
        ];
    }
    logTime('weekly sales+attendance', $timer);

    $timer = microtime(true);
    $recentSql = "
        SELECT
            e.nombre AS type,
            TO_CHAR(e.fecha, 'DD Mon HH24:MI') AS time
        FROM eventos e
        ORDER BY e.fecha DESC
        LIMIT 5
    ";
    $recentStmt = $pdo->query($recentSql);
    $recentRows = $recentStmt->fetchAll() ?: [];

    $recentActivity = [];
    foreach ($recentRows as $index => $row) {
        $recentActivity[] = [
            'id' => $index + 1,
            'type' => (string)$row['type'],
            'description' => 'Evento registrado en el sistema.',
            'time' => (string)$row['time'],
            'color' => 'text-primary',
        ];
    }
    logTime('recent', $timer);

    $timer = microtime(true);
    $categorySql = "
        WITH totals AS (
            SELECT
                e.nombre AS name,
                COALESCE(SUM(ve.total), 0) AS total_evento
            FROM eventos e
            LEFT JOIN ventas_entradas ve
                ON ve.evento_id = e.id
                AND ve.estado IN ('comprada', 'usada')
            GROUP BY e.nombre
        )
        SELECT
            name,
            total_evento,
            SUM(total_evento) OVER () AS total_general
        FROM totals
        ORDER BY total_evento DESC
        LIMIT 5
    ";
    $categoryStmt = $pdo->query($categorySql);
    $categoryRows = $categoryStmt->fetchAll() ?: [];

    $colors = ['#7C3AED', '#3B82F6', '#22C55E', '#EAB308', '#EF4444'];
    $categoryData = [];

    foreach ($categoryRows as $index => $row) {
        $totalGeneral = (float)($row['total_general'] ?? 0);
        $value = 0.0;

        if ($totalGeneral > 0) {
            $value = round((((float)$row['total_evento']) / $totalGeneral) * 100, 2);
        }

        $categoryData[] = [
            'name' => (string)$row['name'],
            'value' => $value,
            'color' => $colors[$index % count($colors)],
        ];
    }
    logTime('category data unica query', $timer);

    $timer = microtime(true);
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
        'recentActivity' => $recentActivity,
    ];
    logTime('response armado final', $timer);

    if ($export === 'csv') {
        sendCsvExport(
            'dashboard-' . date('Ymd_His') . '.csv',
            $metrics,
            $monthlySummary,
            $pastEvents,
            $currentNight,
            $upcomingEvents
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

    logTime('TOTAL dashboard.php', $globalStart);
    sendJson(200, $response);
} catch (Throwable $e) {
    error_log('dashboard.php ERROR: ' . $e->getMessage());

    sendJson(500, [
        'error' => true,
        'message' => 'Error interno del servidor',
    ]);
}
