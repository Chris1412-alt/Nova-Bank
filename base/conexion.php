<?php
declare(strict_types=1);

use PDO;
use PDOException;

// Ocultar errores al usuario y habilitar registro en logs
ini_set('display_errors', '0');
ini_set('log_errors',     '1');
ini_set('error_log',      __DIR__ . '/error.log');

/**
 * Crea y devuelve una instancia PDO conectada a la base de datos bancoNova.
 *
 * @return PDO
 */
function conectar(): PDO {
    $host    = 'localhost';
    $db      = 'banconova';
    $user    = 'root';
    $pass    = '';
    $charset = 'utf8mb4';

    $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, $user, $pass, $opts);
    } catch (PDOException $e) {
        // Registrar el error en error.log
        error_log('Error de conexión a la BD: ' . $e->getMessage());

        // Si se accede directamente al script, responder JSON
        if (php_sapi_name() !== 'cli') {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Error interno de conexión a la base de datos'
            ]);
        }
        exit;
    }
}

// Instanciar y exponer $pdo global para los scripts que incluyan este archivo
$pdo = conectar();
