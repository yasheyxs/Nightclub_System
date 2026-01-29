<?php
header("Content-Type: application/json; charset=utf-8");

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

switch ($uri) {
    case '/api/anticipadas':
        require __DIR__ . '/anticipadas.php';
        break;

    case '/api/entradas':
        require __DIR__ . '/entradas.php';
        break;

    case '/api/eventos':
        require __DIR__ . '/eventos.php';
        break;
    case '/api/listas':
        require __DIR__ . '/listas.php';
        break;

    case '/api/roles':
        require __DIR__ . '/roles.php';
        break;

    case '/api/usuarios':
        require __DIR__ . '/usuarios.php';
        break;

    case '/api/promotores_cupos':
        require __DIR__ . '/promotores_cupos.php';
        break;


    default:
        http_response_code(404);
        echo json_encode([
            'error' => 'Endpoint API no encontrado',
            'path' => $uri
        ]);
}
