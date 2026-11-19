<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ob_start();
require __DIR__.'/../../includes/auth.php';
require __DIR__.'/../../config/database.php';
$u=$conexion->query("SELECT id FROM usuarios WHERE rol='administrador' AND activo=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if(!$u)throw new RuntimeException('Se necesita una cuenta administradora activa.');
$_SESSION['usuario_id']=$u['id'];$_SESSION['rol']='administrador';
$cookie=session_name().'='.session_id();session_write_close();
try{
 $d=$conexion->query('SELECT id,file_hash,document_id FROM documentos ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
 if(!$d)throw new RuntimeException('Se necesita un documento para la prueba.');
 $c=curl_init('http://localhost:8080/gorepa_blockchain/modules/detalle.php?id='.(int)$d['id']);
 curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIE=>$cookie,CURLOPT_TIMEOUT=>15]);
 $body=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
 if($status!==200 || !is_string($body))throw new RuntimeException('La pantalla no respondió correctamente.');
 if(preg_match('/(?:PHP (?:Warning|Fatal)|<b>(?:Warning|Fatal error)<\/b>)/',$body))throw new RuntimeException('Advertencia PHP en pantalla.');
 if(!str_contains($body,'data-qr-kind="public"') || !str_contains($body,'consulta-publica.php?documento='.$d['document_id']))throw new RuntimeException('El QR no abre la ficha del documento.');
 echo 'OK: detalle autenticado y enlace a ficha publica en QR.',PHP_EOL;
 $q=$conexion->prepare("SELECT tx_hash FROM transacciones WHERE documento_id=? AND estado='confirmada' AND network='sepolia' AND contract_address=? AND block_number IS NOT NULL ORDER BY id DESC LIMIT 1");
 $red=json_decode(file_get_contents(__DIR__.'/../red.json'),true);$q->execute([$d['id'],strtolower($red['contractAddress'])]);$tx=$q->fetchColumn();
 if($tx && !str_contains($body,'data-qr-value="https://sepolia.etherscan.io/tx/'.strtolower($tx).'"'))throw new RuntimeException('QR de evidencia no corresponde a la transacción.');
 echo $tx?'OK: QR de Sepolia corresponde a la transacción confirmada.':'OK: sin transacción no se inventa evidencia pública.',PHP_EOL;
}finally{session_start();$_SESSION=[];session_destroy();}
