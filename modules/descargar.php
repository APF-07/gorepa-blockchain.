<?php
// Mantener cualquier salida previa de dependencias fuera del cuerpo binario del PDF.
ob_start();
require_once __DIR__ . '/../includes/documentos.php';
requiereGestionDocumental();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Utiliza el botón de descarga en el detalle del documento.');
}
if (!csrfValido($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Solicitud no válida. Vuelve al detalle del documento.');
}
$id = idDocumento();
require_once __DIR__ . '/../config/database.php';
$doc = buscarDocumento($conexion, $id);
$ruta = archivoDocumental($doc['archivo_ruta']);
if ($ruta === null) {
    http_response_code(404);
    exit('El PDF no está disponible. Contacta al administrador.');
}
$archivo = fopen($ruta, 'rb');
if ($archivo === false || !flock($archivo, LOCK_SH)) {
    if (is_resource($archivo)) fclose($archivo);
    http_response_code(503);
    exit('El archivo no está disponible temporalmente.');
}
try {
    $contexto = hash_init('sha256');
    hash_update_stream($contexto, $archivo);
    $hash = hash_final($contexto);
    $coincide = hash_equals(strtolower($doc['file_hash']), $hash);
    $audit = $conexion->prepare('INSERT INTO auditoria (usuario_id, accion, descripcion) VALUES (?, ?, ?)');
    $audit->execute([$_SESSION['usuario_id'], $coincide ? 'DESCARGA_DOCUMENTO' : 'INTEGRIDAD_DOCUMENTO_FALLIDA', 'Documento ID ' . $id]);
    if (!$coincide) {
        http_response_code(409);
        exit('La huella del PDF no coincide con el registro original. Descarga bloqueada. Contacta al administrador.');
    }
    rewind($archivo);
    $tamano = fstat($archivo)['size'];
    $nombre = preg_replace('/[^a-zA-Z0-9._-]/', '_', $doc['codigo']) . '.pdf';
    ob_end_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    header('Content-Length: ' . $tamano);
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    session_write_close();
    fpassthru($archivo);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'No se pudo completar la descarga de forma segura. Intenta nuevamente.';
} finally {
    flock($archivo, LOCK_UN);
    fclose($archivo);
}
