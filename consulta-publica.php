<?php
ob_start();
require_once __DIR__ . '/includes/consulta-publica.php';
require_once __DIR__ . '/includes/vista.php';
header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('X-Robots-Tag: noindex, nofollow');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405);header('Allow: GET');exit('Solo se permite consultar el registro.'); }
$id = is_string($_GET['documento'] ?? null) ? strtolower($_GET['documento']) : '';
$doc = null;$error = '';$tx = null;
if (!preg_match('/^[a-f0-9]{64}$/D', $id)) { http_response_code(400);$error='El enlace de consulta no es válido.'; }
else {
    try {
        require_once __DIR__ . '/config/database.php';
        $stmt=$conexion->prepare('SELECT id,codigo,tipo,document_id,file_hash,metadata_hash FROM documentos WHERE document_id=? LIMIT 1');$stmt->execute([$id]);$doc=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$doc) { http_response_code(404);$error='No se encontró un documento asociado a este enlace.'; }
        else {
            $config=configuracionBlockchain();
            $stmt=$conexion->prepare("SELECT tx_hash FROM transacciones WHERE documento_id=? AND estado='confirmada' AND network='sepolia' AND contract_address=? AND block_number IS NOT NULL ORDER BY id DESC LIMIT 1");$stmt->execute([$doc['id'],$config['address']]);$valor=$stmt->fetchColumn();
            if (is_string($valor) && preg_match('/^0x[a-f0-9]{64}$/iD',$valor)) $tx=strtolower($valor);
        }
    } catch (Throwable $e) { http_response_code(503);$doc=null;$error='La consulta no está disponible temporalmente.'; }
}
if (($_GET['formato'] ?? '') === 'json') {
    $resultado=$doc ? resultadoPublicoBlockchain($doc,$tx,$config) : ['estado'=>'no_disponible','titulo'=>'CONSULTA NO DISPONIBLE','detalle'=>$error];
    while(ob_get_level())ob_end_clean();header('Content-Type: application/json; charset=UTF-8');echo json_encode($resultado,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
}
$publicUrl=$doc ? urlConsultaPublica($doc['document_id'],$_SERVER) : '';
while(ob_get_level())ob_end_clean();
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Consulta documental · GOREPA Blockchain</title><link rel="stylesheet" href="assets/css/estilos.css?v=<?= filemtime(__DIR__.'/assets/css/estilos.css') ?>"><script src="assets/js/consulta-publica.js?v=<?= filemtime(__DIR__.'/assets/js/consulta-publica.js') ?>" defer></script></head>
<body class="public-document-body"><main class="public-document-main"><header class="public-document-header"><span class="public-document-logo">G</span><div><strong>GOREPA Blockchain</strong><p>Consulta pública de integridad documental</p></div><span class="public-network">Ethereum · Sepolia</span></header><p class="public-academic">Prototipo académico · UNDAC. No es un sistema oficial de GOREPA.</p>
<?php if (!$doc): ?><section class="public-document-card"><h1>Consulta no disponible</h1><p><?= escapar($error) ?></p></section>
<?php else: ?>
<section class="public-document-card"><p class="eyebrow">CONSULTA POR CÓDIGO QR</p><h1>Comprobar registro documental</h1><p>Consulta la evidencia de este documento directamente en Ethereum Sepolia.</p><button type="button" id="public-query" class="action-btn action-primary">Consultar Blockchain</button><p id="public-feedback" class="texto-ayuda" role="status" aria-live="polite">Iniciando consulta a Sepolia…</p></section>
<section id="public-result" class="public-proof public-proof-pending" aria-labelledby="public-title"><h2 id="public-title">CONSULTA PENDIENTE</h2><p id="public-description">El registro no se presenta como verificado hasta completar la comprobación con Ethereum.</p>
<dl class="public-proof-table"><div><dt>Código</dt><dd><?= escapar($doc['codigo']) ?></dd></div><div><dt>Tipo</dt><dd><?= escapar($doc['tipo']) ?></dd></div><div><dt>Hash SHA-256</dt><dd><code id="public-hash"><?= escapar($doc['file_hash']) ?></code></dd></div><div><dt>Emisor en Blockchain</dt><dd id="public-issuer">Pendiente de consulta</dd></div><div><dt>Fecha del registro Blockchain</dt><dd id="public-date">Pendiente de consulta</dd></div><div><dt>Estado del registro</dt><dd><span id="public-state" class="public-state">SIN COMPROBAR</span></dd></div><div><dt>Smart Contract</dt><dd><a href="https://sepolia.etherscan.io/address/<?= escapar($config['address']) ?>" target="_blank" rel="noopener noreferrer"><code><?= escapar($config['address']) ?></code></a></dd></div><div><dt>Red</dt><dd>Ethereum Sepolia · Blockchain pública de pruebas</dd></div><div><dt>Transacción</dt><dd id="public-transaction">Pendiente de consulta</dd></div><div><dt>Confirmaciones</dt><dd id="public-confirmations">Pendiente de consulta</dd></div></dl>
<div class="public-proof-actions"><button type="button" class="action-btn action-secondary" id="public-copy-hash">Copiar hash SHA-256</button><button type="button" class="action-btn action-secondary" id="public-copy-link">Copiar enlace público</button><a id="public-explorer" class="action-btn action-primary" hidden target="_blank" rel="noopener noreferrer">Abrir en Etherscan</a></div>
<p class="public-proof-notice">La coincidencia de hashes respalda técnicamente la integridad respecto a la referencia registrada. No demuestra por sí sola veracidad jurídica, autoría, aprobación oficial ni una firma digital acreditada. No se publica el PDF ni información de las cuentas del sistema.</p>
<noscript><p>Activa JavaScript para consultar Ethereum. Sin esa comprobación no se muestra el documento como verificado.</p></noscript>
</section>
<script type="application/json" id="public-config"><?= json_encode(['endpoint'=>'consulta-publica.php?'.http_build_query(['documento'=>$doc['document_id'],'formato'=>'json']),'url'=>$publicUrl,'hash'=>$doc['file_hash']],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<?php endif; ?><footer class="public-document-footer">Consulta de solo lectura · No requiere MetaMask · No envía transacciones</footer></main></body></html>
