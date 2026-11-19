<?php
require_once __DIR__ . '/../includes/documentos.php';
requiereGestionDocumental();
$id = idDocumento();
require_once __DIR__ . '/../config/database.php';
$doc = buscarDocumento($conexion, $id);
require_once __DIR__ . '/../includes/blockchain.php';
require_once __DIR__ . '/../includes/consulta-publica.php';
$configRed = configuracionBlockchain();
$urlFichaQr = urlConsultaPublica($doc['document_id'], $_SERVER);
$txQr = null;
$errorTxQr = false;
try {
    $consultaQr = $conexion->prepare("SELECT tx_hash FROM transacciones WHERE documento_id = ? AND estado = 'confirmada' AND network = 'sepolia' AND contract_address = ? AND block_number IS NOT NULL ORDER BY id DESC LIMIT 1");
    $consultaQr->execute([$id, $configRed['address']]);
    $hashTxQr = $consultaQr->fetchColumn();
    if (is_string($hashTxQr) && preg_match('/^0x[a-f0-9]{64}$/iD', $hashTxQr)) $txQr = strtolower($hashTxQr);
} catch (PDOException $e) {
    $errorTxQr = true;
}
$tituloPagina = 'Detalle documental';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-heading"><div><p class="eyebrow">DETALLE DOCUMENTAL</p><h1><?= escapar($doc['codigo']) ?></h1><p>Metadatos y huellas del registro original.</p></div><a class="action-btn action-secondary" href="documentos.php"><?= iconoUi('volver') ?><span>Volver al listado</span></a></section>
<section class="dashboard-card">
<div class="section-heading"><h2>Información del documento</h2><span class="badge <?= claseEstado($doc['estado']) ?>"><?= escapar(ucfirst($doc['estado'])) ?></span></div>
<dl class="document-details">
<?php foreach (['tipo' => 'Tipo de documento', 'asunto' => 'Asunto', 'creado_en' => 'Fecha de registro', 'registrado_por' => 'Registrado por', 'archivo_nombre' => 'Nombre original del PDF'] as $campo => $etiqueta): ?><div><dt><?= escapar($etiqueta) ?></dt><dd><?= escapar($doc[$campo] ?? 'No disponible') ?></dd></div><?php endforeach; ?>
</dl>
<div class="hash-panel"><h3>SHA-256 del PDF</h3><code><?= escapar($doc['file_hash']) ?></code><p class="texto-ayuda">Huella guardada al registrar el archivo. No acredita una firma digital ni una verificación independiente en Blockchain.</p></div>
<section class="document-qr-section" aria-labelledby="document-qr-title">
<div class="section-heading"><div><p class="eyebrow">CONSULTA POR QR</p><h2 id="document-qr-title">Huella digital del documento</h2><p class="texto-ayuda">Escanea o descarga los códigos. Un QR facilita consultar la información; no acredita por sí solo autenticidad ni una firma digital.</p></div></div>
<div class="document-qr-grid">
<article class="document-qr-card" data-document-qr data-qr-kind="public" data-qr-value="<?= escapar($urlFichaQr) ?>" data-qr-filename="ficha-documento-<?= (int)$doc['id'] ?>.svg">
<header class="document-qr-heading"><span class="document-qr-tag">DOCUMENTO</span><h3>Consulta pública</h3></header>
<div class="document-qr-image" data-qr-image role="img" aria-label="Código QR de la ficha pública del documento"><span>Preparando código QR…</span></div>
<p class="document-qr-description">Escanea para ver los datos del documento y comprobar su registro en Blockchain.</p>
<div class="document-qr-actions"><a class="action-btn action-primary action-compact" href="<?= escapar($urlFichaQr) ?>" target="_blank" rel="noopener noreferrer"><?= iconoUi('ojo') ?><span>Abrir ficha</span></a><button type="button" class="action-btn action-secondary action-compact" data-qr-download disabled><?= iconoUi('descargar') ?><span>Descargar QR</span></button></div>
<details class="document-qr-reference"><summary>Ver enlace de consulta</summary><a href="<?= escapar($urlFichaQr) ?>" target="_blank" rel="noopener noreferrer"><?= escapar($urlFichaQr) ?></a></details>
<p class="document-qr-status" data-qr-status role="status"></p>
</article>
<?php if ($txQr !== null): $urlTxQr = 'https://sepolia.etherscan.io/tx/' . $txQr; ?>
<article class="document-qr-card" data-document-qr data-qr-kind="transaction" data-qr-value="<?= escapar($urlTxQr) ?>" data-qr-filename="sepolia-documento-<?= (int)$doc['id'] ?>.svg">
<header class="document-qr-heading"><span class="document-qr-tag document-qr-tag-chain">ETHEREUM SEPOLIA</span><h3>Evidencia Blockchain</h3></header>
<div class="document-qr-image" data-qr-image role="img" aria-label="Código QR del enlace a la transacción en Sepolia"><span>Preparando código QR…</span></div>
<p class="document-qr-description">Escanea para consultar la transacción y su estado público en el explorador de Sepolia.</p>
<div class="document-qr-actions"><a class="action-btn action-primary action-compact" href="<?= escapar($urlTxQr) ?>" target="_blank" rel="noopener noreferrer"><?= iconoUi('flecha') ?><span>Ver transacción</span></a><button type="button" class="action-btn action-secondary action-compact" data-qr-download disabled><?= iconoUi('descargar') ?><span>Descargar QR</span></button></div>
<details class="document-qr-reference"><summary>Ver hash de transacción</summary><a href="<?= escapar($urlTxQr) ?>" target="_blank" rel="noopener noreferrer"><?= escapar($txQr) ?></a></details>
<p class="document-qr-status" data-qr-status role="status"></p>
</article>
<?php else: ?><article class="document-qr-card document-qr-pending"><header class="document-qr-heading"><span class="document-qr-tag">PENDIENTE</span><h3>Evidencia Blockchain</h3></header><p class="document-qr-description"><?= $errorTxQr ? 'No se pudo consultar el historial. Actualiza la página para intentarlo nuevamente.' : 'El QR de Sepolia aparecerá cuando este documento tenga una transacción confirmada.' ?></p><a class="action-btn action-secondary action-compact" href="blockchain.php?id=<?= (int)$doc['id'] ?>">Ver registro Blockchain</a></article><?php endif; ?>
</div><p class="document-qr-network-note">Para abrir la ficha desde el celular, conecta el teléfono a la misma red que la PC que aloja el sistema.</p><noscript><p class="texto-ayuda">Activa JavaScript para generar los QR. La huella SHA-256 sigue disponible como texto.</p></noscript>
</section>
<details class="technical-details"><summary>Identificadores técnicos</summary><dl class="document-details"><div><dt>Identificador documental</dt><dd><code><?= escapar($doc['document_id']) ?></code></dd></div><div><dt>SHA-256 de los metadatos</dt><dd><code><?= escapar($doc['metadata_hash']) ?></code></dd></div></dl></details>
<div class="verify-detail-action"><a class="action-btn action-secondary" href="blockchain.php?id=<?= (int)$doc['id'] ?>"><?= iconoUi('lista') ?><span>Registro en Ethereum Sepolia</span></a> <a class="action-btn action-secondary" href="verificar.php?codigo=<?= escapar(rawurlencode($doc['codigo'])) ?>"><?= iconoUi('buscar') ?><span>Verificar otro PDF con este registro</span></a></div>
<div class="download-panel"><div><h3>PDF original protegido</h3><p>La descarga comprueba la integridad del archivo y registra el acceso en auditoría.</p></div>
<form action="descargar.php?id=<?= (int) $doc['id'] ?>" method="post"><input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>"><button type="submit" class="download-button action-btn action-primary"><?= iconoUi('descargar') ?><span>Descargar PDF</span></button></form></div>
<p class="texto-ayuda">Los archivos registrados no se editan ni se reemplazan desde esta aplicación. La conexión con Ethereum requiere el contrato desplegado y configurado en Sepolia.</p>
</section>
<script src="/gorepa_blockchain/assets/vendor/qrcode-generator.js?v=<?= filemtime(__DIR__ . '/../assets/vendor/qrcode-generator.js') ?>" defer></script>
<script src="/gorepa_blockchain/assets/js/documento-qr.js?v=<?= filemtime(__DIR__ . '/../assets/js/documento-qr.js') ?>" defer></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
