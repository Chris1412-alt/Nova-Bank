<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

// Ocultar errores al usuario y registrar en error.log
ini_set('display_errors', '0');
ini_set('log_errors',     '1');
ini_set('error_log',      __DIR__ . '/error.log');

require_once __DIR__ . '/conexion.php';

// Verificar que la conexión $pdo esté disponible
if (!isset($pdo) || !$pdo instanceof PDO) {
    respond(false, 'Error interno: no se pudo establecer la conexión', 500);
}

const RECAPTCHA_SECRET = '6LfKB8QrAAAAAD8AQvB8Xj-W_YpWHQHSXlJLnXnM';

function respond(bool $ok, string $msg, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['success' => $ok, 'message' => $msg]);
    exit;
}

// Solo POST permitido
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(false, 'Método no permitido', 405);
}

// 1) Leer payload JSON
$raw  = file_get_contents('php://input');
error_log("→ RAW JSON recibido en Login.php: {$raw}");
$data = json_decode($raw, true);
if (!is_array($data)) {
    respond(false, 'JSON inválido', 400);
}

// 2) Extraer acción y loguear
$accion = trim((string) ($data['accion'] ?? ''));
error_log("→ Valor de \$accion: “{$accion}”");
if ($accion === '') {
    respond(false, 'Acción no especificada', 400);
}

switch ($accion) {

    case 'registrar':
        // 3) Validar token reCAPTCHA
        $token = trim((string) ($data['g-recaptcha-response'] ?? ''));
        if ($token === '') {
            respond(false, 'CAPTCHA no fue completado', 400);
        }

        // 4) Verificar token con Google
        $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
        $query     = http_build_query([
            'secret'   => RECAPTCHA_SECRET,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
        $resp      = file_get_contents("{$verifyUrl}?{$query}");
        if ($resp === false) {
            respond(false, 'Error al conectar con reCAPTCHA', 500);
        }
        error_log("→ reCAPTCHA raw response: {$resp}");

        $caps = json_decode($resp, true);
        error_log("→ reCAPTCHA parsed: " . print_r($caps, true));
        if (empty($caps['success'])) {
            $errors = implode(', ', $caps['error-codes'] ?? ['unknown']);
            respond(false, "Verificación CAPTCHA fallida: {$errors}", 400);
        }

        // 5) Sanitizar y validar campos
        $u   = trim(filter_var($data['username']       ?? '', FILTER_SANITIZE_SPECIAL_CHARS));
        $fn  = trim(filter_var($data['firstName']      ?? '', FILTER_SANITIZE_SPECIAL_CHARS));
        $ln  = trim(filter_var($data['lastName']       ?? '', FILTER_SANITIZE_SPECIAL_CHARS));
        $td  = trim(filter_var($data['documentType']   ?? '', FILTER_SANITIZE_SPECIAL_CHARS));
        $idn = trim(filter_var($data['identity']       ?? '', FILTER_SANITIZE_NUMBER_INT));
        $bd  = trim((string) ($data['fechaNacimiento'] ?? ''));
        $ph  = trim(filter_var($data['phone']          ?? '', FILTER_SANITIZE_NUMBER_INT));
        $em  = trim(filter_var($data['email']          ?? '', FILTER_SANITIZE_EMAIL));
        $pw  = (string) ($data['password']             ?? '');

        if (!$u || !$fn || !$ln || !$td || !$idn || !$bd || !$ph || !$em || !$pw) {
            respond(false, 'Todos los campos son obligatorios', 422);
        }
        if (strlen($u) < 4 || strlen($u) > 12) {
            respond(false, 'Usuario debe tener entre 4 y 12 caracteres', 422);
        }
        if (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
            respond(false, 'Email inválido', 422);
        }
        if (strlen($pw) < 8) {
            respond(false, 'Contraseña debe tener mínimo 8 caracteres', 422);
        }
        $dt = DateTime::createFromFormat('Y-m-d', $bd);
        if (!$dt || $dt->format('Y-m-d') !== $bd) {
            respond(false, 'Fecha de nacimiento inválida', 422);
        }
        if ((new DateTime())->diff($dt)->y < 18) {
            respond(false, 'Debes tener al menos 18 años para registrarte', 422);
        }

        // 6) Verificar duplicados
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) 
               FROM usuarios 
              WHERE username = :u 
                 OR email    = :e'
        );
        $stmt->execute(['u' => $u, 'e' => $em]);
        if ((int) $stmt->fetchColumn() > 0) {
            respond(false, 'Usuario o email ya registrado', 409);
        }

        // 7) Insertar usuario
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        $ins  = $pdo->prepare(
            'INSERT INTO usuarios
               (username, nombre, apellido, tipo_doc, cedula,
                nacimiento, telefono, email, clave)
             VALUES
               (:u,       :fn,     :ln,      :td,      :idn,
                :bd,      :ph,     :em,      :pw)'
        );
        $ins->execute([
            'u'   => $u,
            'fn'  => $fn,
            'ln'  => $ln,
            'td'  => $td,
            'idn' => $idn,
            'bd'  => $bd,
            'ph'  => $ph,
            'em'  => $em,
            'pw'  => $hash
        ]);

        respond(true, 'Registro exitoso', 201);
        break;

    case 'login':
        // 8) Login (sin CAPTCHA)
        $u  = trim(filter_var($data['username'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS));
        $pw = (string) ($data['password'] ?? '');

        if (!$u || !$pw) {
            respond(false, 'Credenciales requeridas', 422);
        }

        $stmt = $pdo->prepare(
            'SELECT id, clave 
               FROM usuarios 
              WHERE username = :u'
        );
        $stmt->execute(['u' => $u]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($pw, (string)$user['clave'])) {
            respond(false, 'Usuario o contraseña inválida', 401);
        }

        $_SESSION['user_id'] = $user['id'];
        respond(true, 'Login exitoso');
        break;

    default:
        respond(false, 'Acción no reconocida', 400);
        break;
}