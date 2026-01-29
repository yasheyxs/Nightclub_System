<?php
require_once __DIR__ . '/cors.php';

date_default_timezone_set('America/Argentina/Cordoba');

function generarContenidoTicket(string $tipoEntrada, float $monto, bool $incluyeTrago): string
{
    $ancho = 42;
    $contenido = "\n";
    $contenido .= str_repeat("=", 15) . "\n";

    $titulo = "-- SANTAS --";
    $espacios = ($ancho - strlen($titulo)) / 2;
    $contenido .= str_repeat(" ", floor($espacios)) . $titulo . str_repeat(" ", ceil($espacios)) . "\n";

    $contenido .= str_repeat("=", 15) . "\n\n";
    $contenido .= "Entrada: " . $tipoEntrada . "\n";
    $contenido .= "Total: $" . number_format($monto, 0, ',', '.') . "\n";

    if ($incluyeTrago) {
        $contenido .= "INCLUYE TRAGO GRATIS\n";
    }

    $contenido .= "\n" . str_repeat("=", 15) . "\n";
    $contenido .= " Gracias por tu compra \n";
    $contenido .= str_repeat("=", 15) . "\n\n";
    $contenido .= "\n\x1D\x56\x00";

    return $contenido;
}

function enviarAImpresora(string $contenido): void
{
    $archivoTemporal = tempnam(sys_get_temp_dir(), 'ticket_');
    if ($archivoTemporal === false || file_put_contents($archivoTemporal, $contenido) === false) {
        throw new RuntimeException('No se pudo generar el archivo del ticket.');
    }

    $comando = 'cmd.exe /c start /min notepad /p ' . escapeshellarg($archivoTemporal);
    $salida = [];
    $codigo = 0;
    exec($comando, $salida, $codigo);
    @unlink($archivoTemporal);

    if ($codigo !== 0) {
        $mensajeSalida = trim(implode("\n", $salida));
        throw new RuntimeException('Error al enviar el ticket a la impresora: ' . $mensajeSalida);
    }
}

function imprimirTicketCortesia(string $nombreInvitado, string $lista): void
{
    $descripcion = trim($lista) !== ''
        ? "Entrada Gratis - " . $lista
        : "Entrada Gratis";

    if (trim($nombreInvitado) !== '') {
        $descripcion .= " / " . $nombreInvitado;
    }

    $contenido = generarContenidoTicket($descripcion, 0, false);
    enviarAImpresora($contenido);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido.']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Solicitud inválida.']);
        exit;
    }

    $nombreInvitado = isset($input['nombre']) ? trim((string)$input['nombre']) : 'Invitado de lista';
    $lista = isset($input['lista']) ? trim((string)$input['lista']) : 'Lista';

    imprimirTicketCortesia($nombreInvitado, $lista);

    echo json_encode(['mensaje' => 'Ticket gratuito enviado a la impresora.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
