<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ob_start();
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/blockchain.php';
$f=json_decode(file_get_contents(__DIR__.'/fixture.json'),true,512,JSON_THROW_ON_ERROR);
function checkIntegration(bool $ok,string $label):void {if(!$ok)throw new RuntimeException('FALLO: '.$label);echo 'OK: '.$label.PHP_EOL;}
$admin=$conexion->query("SELECT id,nombre FROM usuarios WHERE rol='administrador' AND activo=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$_SESSION['usuario_id']=$admin['id'];$_SESSION['nombre']=$admin['nombre'];$_SESSION['rol']='administrador';$token=tokenCsrf();$cookie=session_name().'='.session_id();session_write_close();
function httpIntegration(string $path,?array $post=null,bool $auth=true):array {
 global $cookie;$c=curl_init('http://localhost:8080/gorepa_blockchain/'.$path);
 curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false]);
 if($auth)curl_setopt($c,CURLOPT_COOKIE,$cookie);
 if($post!==null){curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($post));}
 $body=curl_exec($c);if($body===false)throw new RuntimeException('HTTP no disponible.');$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
 if(preg_match('/(?:PHP (?:Warning|Fatal)|<b>(?:Warning|Fatal error)<\/b>)/',$body))throw new RuntimeException('Advertencias PHP en '.$path);
 return [$status,$body];
}
try {
 checkIntegration(httpIntegration('modules/blockchain.php',null,false)[0]===302,'Módulo requiere sesión');
 [$status,$html]=httpIntegration('modules/blockchain.php');checkIntegration($status===200 && str_contains($html,'Falta desplegar en Sepolia'),'Módulo sin contrato explica configuración pendiente');
 $id=$conexion->query('SELECT id FROM documentos ORDER BY id LIMIT 1')->fetchColumn();
 if($id){
  [$status,$html]=httpIntegration('modules/blockchain.php?id='.$id);checkIntegration($status===200 && str_contains($html,'id="chain-register"') && str_contains($html,'disabled'),'Documento muestra controles bloqueados sin despliegue');
  checkIntegration(httpIntegration('modules/blockchain-api.php',['accion'=>'confirmar','documento_id'=>$id,'csrf_token'=>'invalido'])[0]===403,'API exige CSRF');
  [$status,$json]=httpIntegration('modules/blockchain-api.php',['accion'=>'confirmar','documento_id'=>$id,'csrf_token'=>$token,'tx_hash'=>'0x'.str_repeat('1',64)]);checkIntegration($status===400 && str_contains($json,'desplegar'),'Sin contrato no confirma ni altera documentos');
 }
 foreach(['dashboard','documentos','registrar','verificar','usuarios','auditoria'] as $module)checkIntegration(httpIntegration('modules/'.$module.'.php')[0]===200,'Regresión: '.$module);
 $before=[];foreach(['documentos','transacciones','auditoria'] as $tabla)$before[$tabla]=(int)$conexion->query('SELECT COUNT(*) FROM '.$tabla)->fetchColumn();
 // Son tablas temporales de esta conexión: ocultan las reales y desaparecen al cerrar.
 $conexion->exec("CREATE TEMPORARY TABLE documentos (id INT PRIMARY KEY,codigo VARCHAR(60) UNIQUE,tipo VARCHAR(100),asunto VARCHAR(255),archivo_nombre VARCHAR(255),archivo_ruta VARCHAR(255),file_hash CHAR(64),metadata_hash CHAR(64),document_id CHAR(64) UNIQUE,estado ENUM('pendiente','registrado','reemplazado','error'),usuario_id INT) ENGINE=InnoDB");
 $conexion->exec("CREATE TEMPORARY TABLE transacciones (id INT PRIMARY KEY AUTO_INCREMENT,documento_id INT,tx_hash VARCHAR(66) UNIQUE,contract_address VARCHAR(42),wallet_address VARCHAR(42),network VARCHAR(30),block_number BIGINT UNSIGNED,estado ENUM('pendiente','confirmada','fallida')) ENGINE=InnoDB");
 $conexion->exec('CREATE TEMPORARY TABLE auditoria (id BIGINT PRIMARY KEY AUTO_INCREMENT,usuario_id INT,accion VARCHAR(100),descripcion TEXT) ENGINE=InnoDB');
 $d=$f['document'];
 $stmt=$conexion->prepare("INSERT INTO documentos (id,codigo,tipo,asunto,archivo_nombre,archivo_ruta,file_hash,metadata_hash,document_id,estado,usuario_id) VALUES (1,'QA-LOCAL-CHAIN','Otro documento','Prueba temporal','qa.pdf','no-persistido.pdf',?,?,?,'pendiente',?)");$stmt->execute([$d['file_hash'],$d['metadata_hash'],$d['document_id'],$admin['id']]);
 $p=['estado'=>'pendiente','txHash'=>$f['txHash'],'wallet'=>strtolower($f['tx']['from']),'block'=>null,'confirmations'=>0,'previousId'=>str_repeat('0',64),'supersededBy'=>str_repeat('0',64)];
 guardarPruebaBlockchain($conexion,$d,$p,$f['config'],(int)$admin['id']);
 checkIntegration($conexion->query('SELECT estado FROM documentos WHERE id=1')->fetchColumn()==='pendiente','Transacción pendiente no cambia documento');
 $p['estado']='confirmada';$p['block']=1;$p['confirmations']=2;
 guardarPruebaBlockchain($conexion,$d,$p,$f['config'],(int)$admin['id']);
 checkIntegration($conexion->query('SELECT estado FROM documentos WHERE id=1')->fetchColumn()==='registrado','Solo prueba confirmada registra documento');
 guardarPruebaBlockchain($conexion,$d,$p,$f['config'],(int)$admin['id']);
 checkIntegration((int)$conexion->query('SELECT COUNT(*) FROM transacciones')->fetchColumn()===1 && (int)$conexion->query('SELECT COUNT(*) FROM auditoria')->fetchColumn()===2,'Confirmación repetida no duplica transacción ni auditoría');
 $p['supersededBy']=str_repeat('a',64);guardarPruebaBlockchain($conexion,$d,$p,$f['config'],(int)$admin['id']);
 checkIntegration($conexion->query('SELECT estado FROM documentos WHERE id=1')->fetchColumn()==='reemplazado','Consulta de sustitución conserva estado reemplazado');
 $stale=$d;$stale['metadata_hash']=str_repeat('f',64);$rejected=false;
 try{guardarPruebaBlockchain($conexion,$stale,$p,$f['config'],(int)$admin['id']);}catch(DomainException $e){$rejected=true;}
 checkIntegration($rejected && !$conexion->inTransaction(),'Registro cambiado se rechaza y revierte la transacción');
 checkIntegration((int)$conexion->query('SELECT COUNT(*) FROM transacciones')->fetchColumn()===1,'Rechazo no crea transacciones');
 echo 'Persistencia comprobada solo en tablas temporales; datos reales intactos.',PHP_EOL;
} finally {
 httpIntegration('logout.php',['csrf_token'=>$token]);
}
