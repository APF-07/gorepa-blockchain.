<?php
require_once __DIR__ . '/auth.php';
function tiposDocumentales(): array {
    return ['Resolución administrativa', 'Resolución ejecutiva regional', 'Informe administrativo', 'Otro documento'];
}
function idDocumento(): int {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) {
        http_response_code(400);
        exit('Identificador de documento no válido.');
    }
    return $id;
}
function buscarDocumento(PDO $conexion, int $id): array {
    $stmt = $conexion->prepare('SELECT d.*, u.nombre AS registrado_por FROM documentos d LEFT JOIN usuarios u ON u.id = d.usuario_id WHERE d.id = ?');
    $stmt->execute([$id]);
    $documento = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$documento) {
        http_response_code(404);
        exit('Documento no encontrado.');
    }
    return $documento;
}
function archivoDocumental(string $ruta): ?string {
    $base = realpath(__DIR__ . '/../storage/documentos');
    $archivo = realpath(__DIR__ . '/../' . $ruta);
    if ($base === false || $archivo === false) return null;
    $prefijo = $base . DIRECTORY_SEPARATOR;
    $dentro = DIRECTORY_SEPARATOR === '\\'
        ? strncasecmp($archivo, $prefijo, strlen($prefijo)) === 0
        : strncmp($archivo, $prefijo, strlen($prefijo)) === 0;
    if (!$dentro || !is_file($archivo) || !is_readable($archivo) || strtolower(pathinfo($archivo, PATHINFO_EXTENSION)) !== 'pdf') return null;
    return $archivo;
}
