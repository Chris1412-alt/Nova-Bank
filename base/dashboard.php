<?php
session_start();

// 1) Validar que sea petición AJAX POST
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    http_response_code(400);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'Solicitud inválida']);
    exit;
}

// 2) Cabecera JSON
header('Content-Type: application/json; charset=UTF-8');

// 3) Verificar sesión de usuario
if (
    empty($_SESSION['user']) ||
    !isset(
        $_SESSION['user']['name'],
        $_SESSION['user']['balance'],
        $_SESSION['user']['card_number']
    )
) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// 4) Extraer y sanear datos
$user    = $_SESSION['user'];
$name    = htmlspecialchars($user['name'],    ENT_QUOTES, 'UTF-8');
$balance = number_format((float)$user['balance'], 2, '.', '');
$digits  = substr(preg_replace('/\D+/', '', $user['card_number']), -4);

// 5) Devolver JSON limpio
echo json_encode([
    'name'           => $name,
    'balance'        => $balance,
    'cardLastDigits' => $digits,
], JSON_UNESCAPED_UNICODE);

exit;