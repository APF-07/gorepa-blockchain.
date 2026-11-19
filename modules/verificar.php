<?php
require_once __DIR__ . '/../includes/documentos.php';
requiereGestionDocumental();
require_once __DIR__ . '/../includes/verificacion.php';
$codigo = isset($_GET['codigo']) && is_string($_GET['codigo']) ? substr(trim($_GET['codigo']), 0, 60) : '';
$resultado = null;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = isset($_POST['codigo']) && is_string($_POST['codigo']) ? trim($_POST['codigo']) : '';
    try {
        if (!csrfValido($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            throw new DomainException('Solicitud no válida. Actualiza la página y selecciona nuevamente el PDF.');
        }
        if (strlen($codigo) > 60) throw new DomainException('El código admite un máximo de 60 bytes.');
        if (!isset($_FILES['documento']) || !is_array($_FILES['documento'])) throw new DomainException('Selecciona un PDF de hasta 5 MB. Si la carga excede el límite del servidor, utiliza un archivo más pequeño.');
        $hash = hashPdfVerificacion($_FILES['documento']);
        $comparacion = compararHuellaDocumental($conexion, $hash, $codigo);
        auditarVerificacion($conexion, (int) $_SESSION['usuario_id'], $comparacion, $codigo === '' ? 'busqueda_por_hash' : 'comparacion_por_codigo');
        $resultado = $comparacion;
    } catch (DomainException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        $error = 'No se pudo completar y registrar la verificación. Intenta nuevamente más tarde.';
    }
}
$catalogo = [];
$errorCatalogo = false;
try {
    $catalogo = $conexion->query('SELECT codigo, archivo_nombre, asunto FROM documentos ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorCatalogo = true;
}
$tituloPagina = 'Verificar documento';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-heading"><div><p class="eyebrow">INTEGRIDAD DOCUMENTAL · OFF-CHAIN</p><h1>Verificar un documento</h1><p>Compara la huella SHA-256 de un PDF con los registros guardados en MySQL.</p></div><a class="action-btn action-secondary" href="documentos.php"><?= iconoUi('lista') ?><span>Ver documentos</span></a></section>
<div class="verification-scope"><strong>Verificación frente a MySQL</strong><p>Este módulo comprueba igualdad de archivos respecto al registro de la aplicación. No consulta Ethereum, no acredita autoría y no valida firmas digitales.</p></div>
<?php if ($error): ?><p class="error" role="alert"><?= escapar($error) ?></p><?php endif; ?>
<section class="dashboard-card verification-form-card"><div class="section-heading"><div><h2>Selecciona el PDF que deseas comprobar</h2><p>El archivo se procesa temporalmente y no reemplaza ningún documento registrado.</p></div></div>
<form method="post" enctype="multipart/form-data" class="verification-form">
<input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
<input type="hidden" name="MAX_FILE_SIZE" value="5242880">
<div class="verification-document-picker">
<label for="codigo">Documento registrado <span class="optional-label">Opcional</span></label>
<?php if ($errorCatalogo): ?>
<input id="codigo" name="codigo" maxlength="60" placeholder="Código del documento" value="<?= escapar($codigo) ?>" aria-describedby="codigo-ayuda">
<p class="texto-ayuda" role="status">No se pudo cargar la lista. Puedes escribir el código o intentarlo nuevamente.</p>
<?php else: ?>
<div id="document-search-container" hidden><label for="document-search" class="sr-only">Buscar documento por código, archivo o asunto</label><input id="document-search" type="search" placeholder="Buscar por código, archivo o asunto…" autocomplete="off" aria-controls="codigo" aria-describedby="document-search-count"></div>
<select id="codigo" name="codigo" aria-describedby="codigo-ayuda" data-document-picker>
<option value="">Buscar automáticamente por SHA-256 (sin elegir documento)</option>
<?php $codigoDisponible = $codigo === ''; foreach ($catalogo as $opcion): $seleccionado = $codigo === $opcion['codigo']; if ($seleccionado) $codigoDisponible = true; ?>
<option value="<?= escapar($opcion['codigo']) ?>" data-search="<?= escapar($opcion['codigo'].' '.$opcion['archivo_nombre'].' '.$opcion['asunto']) ?>" <?= $seleccionado ? 'selected' : '' ?>><?= escapar($opcion['codigo'].' — '.$opcion['archivo_nombre']) ?></option>
<?php endforeach; ?>
<?php if (!$codigoDisponible): ?><option value="<?= escapar($codigo) ?>" selected><?= escapar($codigo) ?> — Código no encontrado en la lista</option><?php endif; ?>
</select>
<p id="document-search-count" class="texto-ayuda" role="status" aria-live="polite"><?= count($catalogo) ?> documento(s) registrado(s).</p>
<?php endif; ?>
<p id="codigo-ayuda" class="texto-ayuda">Elige el código y nombre del archivo que quieres comprobar. Después carga el PDF original o una copia para comparar. La primera opción busca coincidencias por SHA-256 sin elegir un registro.</p>
</div>
<div><label for="documento">Archivo PDF</label><input type="file" id="documento" name="documento" accept=".pdf,application/pdf" aria-describedby="pdf-ayuda" required><p id="pdf-ayuda" class="texto-ayuda">Máximo 5 MB. Usa documentos ficticios. Se compara el contenido completo del archivo, no su nombre.</p></div>
<div class="verification-form-actions"><button type="submit" class="action-btn action-primary"><?= iconoUi('buscar') ?><span>Verificar integridad</span></button><a class="action-btn action-ghost" href="verificar.php">Nueva consulta</a></div>
</form></section>
<?php if ($resultado):
$mensajes = [
    'coincide'=>['Coincidencia exacta con MySQL', 'El SHA-256 del archivo cargado coincide con la huella almacenada. No se detectaron diferencias frente a la huella del registro consultado.'],
    'diferente'=>['El PDF es diferente al registrado', 'Las huellas no coinciden. El archivo pudo modificarse o puede ser otro PDF. Comprueba que hayas elegido el documento y el código correctos.'],
    'sin_coincidencias'=>['No se encontró una huella coincidente', 'Este SHA-256 no aparece en los registros consultados. Sin un código de referencia no se puede distinguir entre un documento nuevo y uno modificado.'],
    'codigo_no_encontrado'=>['Código documental no encontrado', 'No existe un registro con ese código. Revisa el código o deja el campo vacío para buscar por la huella del PDF.']
];
[$tituloResultado, $descripcionResultado] = $mensajes[$resultado['estado']];
$claseResultado = $resultado['estado'] === 'coincide' ? 'match' : ($resultado['estado'] === 'diferente' ? 'mismatch' : 'unknown');
?>
<section class="dashboard-card verification-result result-<?= $claseResultado ?>" aria-labelledby="titulo-resultado" role="status">
<div class="result-heading"><span class="result-symbol" aria-hidden="true"><?= $claseResultado === 'match' ? '✓' : ($claseResultado === 'mismatch' ? '!' : '?') ?></span><div><p class="eyebrow">RESULTADO · MYSQL</p><h2 id="titulo-resultado"><?= escapar($tituloResultado) ?></h2></div></div>
<p class="result-description"><?= escapar($descripcionResultado) ?></p>
<?php if ($codigo !== ''): ?><p class="texto-ayuda">Código consultado: <strong><?= escapar($codigo) ?></strong></p><?php endif; ?>
<div class="hash-panel"><h3>SHA-256 del PDF cargado</h3><code><?= escapar($resultado['hash']) ?></code></div>
<?php if ($resultado['documentos']): ?>
<div class="verification-matches"><h3><?= $resultado['estado'] === 'diferente' ? 'Registro de referencia' : 'Documentos coincidentes' ?></h3>
<?php foreach ($resultado['documentos'] as $doc): ?><article class="matched-document"><div class="section-heading"><div><strong><?= escapar($doc['codigo']) ?></strong><span class="cell-secondary"><?= escapar($doc['tipo']) ?></span></div><a class="action-btn action-secondary action-compact" href="detalle.php?id=<?= (int) $doc['id'] ?>"><?= iconoUi('ojo') ?><span>Ver detalle</span></a></div><p><?= escapar($doc['asunto']) ?></p><span class="texto-ayuda">SHA-256 guardado en MySQL</span><code><?= escapar($doc['file_hash']) ?></code></article><?php endforeach; ?>
<?php if ($resultado['total'] > 20): ?><p class="texto-ayuda">Se muestran los 20 documentos más recientes de <?= (int) $resultado['total'] ?> coincidencias.</p><?php endif; ?>
</div><?php endif; ?>
<p class="verification-audit-note">Consulta registrada en auditoría. Fuente de comparación: MySQL. Para consultar Ethereum, utiliza Registro Blockchain.</p>
</section>
<?php endif; ?>
<section class="verification-help"><h2>Cómo interpretar la comparación</h2><p>Un PDF modificado, incluso si conserva su apariencia, genera normalmente una huella distinta. Renombrar el archivo conserva su huella si no cambian sus bytes. El resultado depende de la información guardada en MySQL y no demuestra que el documento haya sido aprobado por GOREPA.</p></section>
<script src="/gorepa_blockchain/assets/js/verificar.js?v=<?= filemtime(__DIR__ . '/../assets/js/verificar.js') ?>" defer></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
