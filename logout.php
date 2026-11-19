<?php
require_once __DIR__ . '/includes/auth.php';

requiereLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Utiliza el botón Cerrar sesión del menú.');
}
if (!csrfValido($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Solicitud no válida. Actualiza la página e inténtalo nuevamente.');
}
cerrarSesionActual();

header('Location: /gorepa_blockchain/login.php');
exit;
