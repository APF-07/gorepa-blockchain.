<?php

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/'
    ]);

    session_start();
}

function cerrarSesionActual(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000, 'path' => $params['path'],
            'domain' => $params['domain'], 'secure' => $params['secure'],
            'httponly' => $params['httponly'], 'samesite' => $params['samesite'] ?? 'Lax'
        ]);
    }
    session_destroy();
}
function requiereLogin(): void {
    static $validado = false;
    if (empty($_SESSION['usuario_id'])) {
        header('Location: /gorepa_blockchain/login.php');
        exit;
    }
    if ($validado) return;
    global $conexion;
    // La configuración existente contiene espacios: no enviarlos antes de las cabeceras.
    ob_start();
    require_once __DIR__ . '/../config/database.php';
    ob_end_clean();
    try {
        $stmt = $conexion->prepare('SELECT nombre, rol, activo FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['usuario_id']]);
        $usuarioActual = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        http_response_code(503);
        exit('No se pudo validar la sesión. Intenta nuevamente más tarde.');
    }
    if (!$usuarioActual || (int) $usuarioActual['activo'] !== 1) {
        cerrarSesionActual();
        header('Location: /gorepa_blockchain/login.php?sesion=finalizada');
        exit;
    }
    $_SESSION['nombre'] = $usuarioActual['nombre'];
    $_SESSION['rol'] = $usuarioActual['rol'];
    $validado = true;
}

function requiereAdministrador(): void {
    requiereLogin();

    if (($_SESSION['rol'] ?? '') !== 'administrador') {
        http_response_code(403);
        exit('Acceso denegado.');
    }
}

function requiereGestionDocumental(): void {
    requiereLogin();
    if (!in_array($_SESSION['rol'] ?? '', ['administrador', 'operador'], true)) {
        http_response_code(403);
        exit('No tienes permiso para consultar documentos.');
    }
}
function tokenCsrf(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrfValido($token): bool {
    return is_string($token) && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
