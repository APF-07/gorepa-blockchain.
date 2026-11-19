<?php
if(PHP_SAPI!=='cli')exit;
ob_start();require __DIR__.'/../../config/database.php';
require __DIR__.'/../../includes/consulta-publica.php';
function pageRead(string $path,bool $post=false):array{
 $c=curl_init('http://localhost:8080/gorepa_blockchain/'.$path);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60,CURLOPT_FOLLOWLOCATION=>false]);
 if($post){curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,'');}
 $body=curl_exec($c);$code=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
 if($body===false)throw new RuntimeException('HTTP local no disponible.');
 if(preg_match('/(?:PHP (?:Warning|Fatal)|<b>(?:Warning|Fatal error)<\/b>)/',$body))throw new RuntimeException('Advertencia PHP en consulta publica.');
 return [$code,$body];
}
function good(bool $v,string $m):void{if(!$v)throw new RuntimeException('FALLO: '.$m);echo 'OK: '.$m.PHP_EOL;}
$before=[];foreach(['documentos','transacciones','auditoria'] as $t)$before[$t]=$conexion->query('SELECT COUNT(*) FROM '.$t)->fetchColumn();
$d=$conexion->query('SELECT document_id,file_hash FROM documentos ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);if(!$d)throw new RuntimeException('Se necesita un documento.');
$path='consulta-publica.php?documento='.$d['document_id'];
[$code,$html]=pageRead($path);good($code===200 && str_contains($html,'CONSULTA PENDIENTE'),'Ficha anonima carga sin login y sin afirmar verificacion inicial');
good(str_contains($html,$d['file_hash']) && str_contains($html,'public-proof-table'),'Ficha muestra SHA-256 y tabla documental');
foreach(['password_hash','csrf_token','archivo_ruta','registrado_por'] as $campo)good(!str_contains($html,$campo),'No expone campo privado '.$campo);
[$code,$body]=pageRead($path.'&formato=json');$json=json_decode($body,true,512,JSON_THROW_ON_ERROR);
echo 'Respuesta publica: HTTP '.$code.'; estado '.($json['estado']??'desconocido').PHP_EOL;
good($code===200 && $json['estado']==='verificado','Consulta real a Sepolia confirma ficha');
good(preg_match('/^0x[a-f0-9]{40}$/iD',$json['emisor'])===1 && $json['unix']>0 && $json['confirmaciones']>=2,'Emisor, fecha y confirmaciones proceden de Blockchain');
good(pageRead('consulta-publica.php?documento=invalido')[0]===400,'Referencia invalida rechazada');
good(pageRead('consulta-publica.php?documento='.str_repeat('f',64))[0]===404,'Registro inexistente responde 404');
good(pageRead($path,true)[0]===405,'Escritura POST rechazada');
foreach($before as $t=>$n)good($conexion->query('SELECT COUNT(*) FROM '.$t)->fetchColumn()===$n,'Sin cambios en '.$t);
$base=baseConsultaPublica(['SERVER_ADDR'=>'127.0.0.1','SERVER_PORT'=>8080]);echo 'Base de QR configurada: '.$base.PHP_EOL;
