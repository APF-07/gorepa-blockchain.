<?php
// Configuración exclusiva del contenedor; no contiene credenciales locales.
$host = getenv('DB_HOST');
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
if (!$host || !$name || !$user || $password === false || !ctype_digit($port)) {
    http_response_code(503);
    exit('Falta configurar la conexión a la base de datos.');
}
try {
    $conexion = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (PDOException $e) {
    http_response_code(503);
    exit('No se pudo conectar a la base de datos.');
}
