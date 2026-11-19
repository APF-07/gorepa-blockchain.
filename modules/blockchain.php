<?php
require_once __DIR__ . '/../includes/documentos.php';
requiereGestionDocumental();
require_once __DIR__ . '/../includes/blockchain.php';
$config=configuracionBlockchain();
$doc=null;$transacciones=[];
if (isset($_GET['id'])) {
    $doc=buscarDocumento($conexion,idDocumento());
    $stmt=$conexion->prepare('SELECT tx_hash,estado,block_number,creado_en,contract_address FROM transacciones WHERE documento_id=? ORDER BY id DESC LIMIT 20');$stmt->execute([$doc['id']]);$transacciones=$stmt->fetchAll(PDO::FETCH_ASSOC);
}
$documentos=$conexion->query('SELECT id,codigo,estado FROM documentos ORDER BY id DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
$tituloPagina='Registro Blockchain';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-heading"><div><p class="eyebrow">ON-CHAIN · ETHEREUM SEPOLIA</p><h1>Registro Blockchain</h1><p>Firma desde tu billetera y consulta la huella del documento en Ethereum.</p></div><a class="action-btn action-secondary" href="documentos.php"><?= iconoUi('lista') ?><span>Ver documentos</span></a></section>
<div class="verification-scope"><strong>Solo red de pruebas Sepolia · Sin fondos reales</strong><p>Se registran identificadores y hashes. Los PDF y los datos personales permanecen fuera de Ethereum. La billetera solo firma cuando confirmas la operación.</p></div>
<?php if ($config['address']===''): ?><div class="chain-setup"><h2>Conexión preparada · Falta desplegar en Sepolia</h2><p>El contrato probado en Remix VM pertenece a una red de práctica aislada. Para usar esta pantalla falta instalar MetaMask, desplegar el contrato en Sepolia con ETH de prueba gratuito y configurar su dirección pública.</p><p>Mientras tanto, los documentos conservan su estado actual y no se envían transacciones desde esta pantalla.</p></div><?php else: ?><p class="texto-ayuda">Contrato: <a href="https://sepolia.etherscan.io/address/<?= escapar($config['address']) ?>" target="_blank" rel="noopener noreferrer"><?= escapar($config['address']) ?></a> · Confirmaciones requeridas: <?= $config['confirmations'] ?></p><?php endif; ?>
<?php if (!$doc): ?>
<section class="dashboard-card"><h2>Selecciona un documento</h2><p class="texto-ayuda">Últimos 20 registros. También puedes abrir cualquier documento desde su página de detalle.</p><div class="chain-documents"><?php foreach($documentos as $item): ?><a class="chain-document-choice" href="blockchain.php?id=<?= (int)$item['id'] ?>"><strong><?= escapar($item['codigo']) ?></strong><span><?= escapar(ucfirst($item['estado'])) ?> →</span></a><?php endforeach; ?><?php if (!$documentos): ?><p>No hay documentos registrados.</p><?php endif; ?></div></section>
<?php else: ?>
<section class="dashboard-card"><div class="section-heading"><div><h2><?= escapar($doc['codigo']) ?></h2><p class="texto-ayuda"><?= escapar($doc['tipo']) ?></p></div><a class="action-btn action-secondary" href="detalle.php?id=<?= (int)$doc['id'] ?>">Ver detalle</a></div><div class="hash-panel"><h3>SHA-256 del PDF en MySQL</h3><code><?= escapar($doc['file_hash']) ?></code></div>
<div id="chain-app" class="chain-app">
<div class="chain-wallet"><button type="button" id="chain-connect" class="action-btn action-secondary" <?= $config['address']===''?'disabled':'' ?>>Conectar MetaMask</button><span id="chain-wallet-label">Billetera sin conectar</span></div>
<p id="chain-status" class="chain-status" role="status" aria-live="polite">Pulsa Conectar MetaMask para comprobar la billetera de este perfil de Chrome.</p>
<div class="chain-register"><label for="chain-previous">Código anterior si este documento lo sustituye <span class="optional-label">Opcional</span></label><input id="chain-previous" maxlength="60" placeholder="Dejar vacío para un registro nuevo"><p class="texto-ayuda">El registro anterior debe existir en el contrato. La sustitución conserva el historial.</p><div class="chain-actions"><button type="button" id="chain-register" class="action-btn action-primary" disabled>Registrar en Sepolia</button><button type="button" id="chain-read" class="action-btn action-secondary" disabled>Consultar Ethereum</button></div></div>
<div class="chain-verify"><label for="chain-pdf">Comparar un PDF con Ethereum</label><input type="file" id="chain-pdf" accept=".pdf,application/pdf"><p class="texto-ayuda">Hasta 5 MB. El navegador calcula su hash y lo compara directamente con el registro del contrato.</p><button type="button" id="chain-verify" class="action-btn action-secondary" disabled>Comparar PDF con Ethereum</button></div>
<div class="chain-recover"><label for="chain-tx">Hash de transacción para confirmar o recuperar</label><input id="chain-tx" placeholder="0x…" maxlength="66" autocomplete="off"><button type="button" id="chain-confirm" class="action-btn action-secondary" <?= $config['address']===''?'disabled':'' ?>>Comprobar y guardar confirmación</button><p class="texto-ayuda">Si cerraste la página tras firmar, introduce aquí el hash. PHP comprueba la red, la transacción, su evento y los hashes antes de actualizar MySQL.</p></div>
<pre id="chain-output" class="chain-output" hidden></pre>
</div></section>
<section class="dashboard-card chain-history"><h2>Historial de transacciones del documento</h2><p class="texto-ayuda">Estados guardados tras la comprobación del servidor. Las confirmaciones no equivalen a finalidad absoluta y pueden requerir revisión ante reorganizaciones de la red.</p><div class="tabla-contenedor"><table class="tabla-documentos"><thead><tr><th>Transacción Sepolia</th><th>Estado</th><th>Bloque</th><th>Fecha</th></tr></thead><tbody><?php foreach($transacciones as $tx): ?><tr><td><a class="chain-tx-link" href="https://sepolia.etherscan.io/tx/<?= escapar($tx['tx_hash']) ?>" target="_blank" rel="noopener noreferrer"><?= escapar($tx['tx_hash']) ?></a></td><td><?= escapar($tx['estado']) ?></td><td><?= escapar($tx['block_number'] ?? 'Pendiente') ?></td><td><?= escapar($tx['creado_en']) ?></td></tr><?php endforeach; ?><?php if(!$transacciones): ?><tr><td colspan="4">Todavía no hay transacciones guardadas para este documento.</td></tr><?php endif; ?></tbody></table></div></section>
<script type="application/json" id="chain-config"><?= json_encode(['address'=>$config['address'],'confirmations'=>$config['confirmations'],'documentoId'=>(int)$doc['id'],'documentId'=>'0x'.$doc['document_id'],'fileHash'=>'0x'.$doc['file_hash'],'metadataHash'=>'0x'.$doc['metadata_hash'],'csrf'=>tokenCsrf()],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<script src="/gorepa_blockchain/assets/vendor/ethers.umd.min.js?v=<?= filemtime(__DIR__.'/../assets/vendor/ethers.umd.min.js') ?>" defer></script>
<script src="/gorepa_blockchain/blockchain/contrato.js?v=<?= filemtime(__DIR__.'/../blockchain/contrato.js') ?>" defer></script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
