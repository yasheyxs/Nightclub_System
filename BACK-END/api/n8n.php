<?php
require_once __DIR__ . '/cors.php';
$allowedIP = '167.86.117.13';


$endpoint = $_GET['endpoint'] ?? null;

$endpoints = [
    "GET /anticipadas" => "https://santas-phpback.4jkbnu.easypanel.host/anticipadas",
    "POST /anticipadas" => "https://santas-phpback.4jkbnu.easypanel.host/anticipadas",
    "GET /dashboard" => "https://santas-phpback.4jkbnu.easypanel.host/dashboard",
    "GET /entradas" => "https://santas-phpback.4jkbnu.easypanel.host/entradas",
    "PUT /entradas?id={id}" => "https://santas-phpback.4jkbnu.easypanel.host/entradas?id={id}",
    "POST /entradas" => "https://santas-phpback.4jkbnu.easypanel.host/entradas",
    "DELETE /entradas?id={id}" => "https://santas-phpback.4jkbnu.easypanel.host/entradas?id={id}",
    "GET /eventos?upcoming=1" => "https://santas-phpback.4jkbnu.easypanel.host/eventos?upcoming=1",
    "GET /eventos?calendar=1" => "https://santas-phpback.4jkbnu.easypanel.host/eventos?calendar=1",
    "GET /eventos" => "https://santas-phpback.4jkbnu.easypanel.host/eventos",
    "POST /eventos" => "https://santas-phpback.4jkbnu.easypanel.host/eventos",
    "PUT /eventos?id={id}" => "https://santas-phpback.4jkbnu.easypanel.host/eventos?id={id}",
    "DELETE /eventos?id={id}" => "https://santas-phpback.4jkbnu.easypanel.host/eventos?id={id}",
    "POST /imprimir_ticket_gratis" => "https://santas-phpback.4jkbnu.easypanel.host/imprimir_ticket_gratis",
    "GET /listas" => "https://santas-phpback.4jkbnu.easypanel.host/listas",
    "POST /listas" => "https://santas-phpback.4jkbnu.easypanel.host/listas",
    "PUT /listas?id={id}" => "https://santas-phpback.4jkbnu.easypanel.host/listas?id={id}",
    "DELETE /listas?id={id}" => "https://santas-phpback.4jkbnu.easypanel.host/listas?id={id}",
    "POST /login" => "https://santas-phpback.4jkbnu.easypanel.host/login",
    "POST /password_reset" => "https://santas-phpback.4jkbnu.easypanel.host/password_reset",
    "GET /roles" => "https://santas-phpback.4jkbnu.easypanel.host/roles",
    "GET /usuarios" => "https://santas-phpback.4jkbnu.easypanel.host/usuarios",
    "POST /usuarios" => "https://santas-phpback.4jkbnu.easypanel.host/usuarios",
    "PUT /usuarios?id={id}" => "https://santas-phpback.4jkbnu.easypanel.host/usuarios?id={id}",
    "DELETE /usuarios?id={id}" => "https://santas-phpback.4jkbnu.easypanel.host/usuarios?id={id}",
    "GET /venta_entradas" => "https://santas-phpback.4jkbnu.easypanel.host/venta_entradas",
    "POST /venta_entradas" => "https://santas-phpback.4jkbnu.easypanel.host/venta_entradas",
];

// Devolver la lista de endpoints
echo json_encode($endpoints);
