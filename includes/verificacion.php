<?php
function hashPdfVerificacion(array $archivo): string {
    if (!isset($archivo['error']) || !is_int($archivo['error'])) throw new DomainException('Selecciona un archivo PDF válido.');
    if (in_array($archivo['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) throw new DomainException('El PDF supera el tamaño permitido. El máximo de la aplicación es 5 MB.');
    if ($archivo['error'] !== UPLOAD_ERR_OK) throw new DomainException('No se pudo recibir el PDF. Selecciona el archivo e inténtalo nuevamente.');
    $temporal = $archivo['tmp_name'] ?? null;
    $nombre = $archivo['name'] ?? null;
    if (!is_string($temporal) || !is_string($nombre) || !is_uploaded_file($temporal)) throw new DomainException('Archivo no válido.');
    $tamano = filesize($temporal);
    if ($tamano === false || $tamano <= 0 || $tamano > 5 * 1024 * 1024) throw new DomainException('El PDF debe tener contenido y un tamaño máximo de 5 MB.');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $cabecera = file_get_contents($temporal, false, null, 0, 5);
    if (strtolower(pathinfo($nombre, PATHINFO_EXTENSION)) !== 'pdf' || $finfo->file($temporal) !== 'application/pdf' || $cabecera !== '%PDF-') throw new DomainException('Solo se permiten archivos PDF válidos.');
    $hash = hash_file('sha256', $temporal);
    if ($hash === false) throw new RuntimeException('No se pudo calcular la huella del PDF.');
    return $hash;
}
function compararHuellaDocumental(PDO $db, string $hash, string $codigo): array {
    if (!preg_match('/^[a-f0-9]{64}$/D', $hash)) throw new DomainException('Huella SHA-256 no válida.');
    if (strlen($codigo) > 60) throw new DomainException('El código admite un máximo de 60 bytes.');
    if ($codigo !== '') {
        $stmt = $db->prepare('SELECT id, codigo, tipo, asunto, file_hash FROM documentos WHERE codigo = ? LIMIT 1');
        $stmt->execute([$codigo]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$doc) return ['estado'=>'codigo_no_encontrado', 'documentos'=>[], 'total'=>0, 'hash'=>$hash];
        $coincide = hash_equals(strtolower($doc['file_hash']), $hash);
        return ['estado'=>$coincide ? 'coincide' : 'diferente', 'documentos'=>[$doc], 'total'=>1, 'hash'=>$hash];
    }
    $stmt = $db->prepare('SELECT COUNT(*) FROM documentos WHERE file_hash = ?');
    $stmt->execute([$hash]);
    $total = (int) $stmt->fetchColumn();
    $stmt = $db->prepare('SELECT id, codigo, tipo, asunto, file_hash FROM documentos WHERE file_hash = ? ORDER BY id DESC LIMIT 20');
    $stmt->execute([$hash]);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return ['estado'=>$documentos ? 'coincide' : 'sin_coincidencias', 'documentos'=>$documentos, 'total'=>$total, 'hash'=>$hash];
}
function auditarVerificacion(PDO $db, int $usuarioId, array $resultado, string $modo): void {
    $descripcion = json_encode([
        'resultado'=>$resultado['estado'],
        'modo'=>$modo,
        'sha256_cargado'=>$resultado['hash'],
        'documentos_ids'=>array_map('intval', array_column($resultado['documentos'], 'id')),
        'coincidencias'=>$resultado['estado'] === 'coincide' ? $resultado['total'] : 0,
        'fuente'=>'MySQL (off-chain)'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $stmt = $db->prepare('INSERT INTO auditoria (usuario_id, accion, descripcion) VALUES (?, ?, ?)');
    $stmt->execute([$usuarioId, 'VERIFICACION_DOCUMENTO', $descripcion]);
}
