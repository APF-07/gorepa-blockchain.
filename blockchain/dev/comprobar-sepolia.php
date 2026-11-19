<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../../includes/blockchain.php';
$config=configuracionBlockchain();
if ($config['address']==='') { fwrite(STDERR,'Falta configurar el contrato.'.PHP_EOL);exit(1); }
try {
 $chain=enteroHexBlockchain(rpcSepolia($config,'eth_chainId',[]));
 if ($chain!==11155111) throw new RuntimeException('Red incorrecta.');
 $code=rpcSepolia($config,'eth_getCode',[$config['address'],'latest']);
 if (!is_string($code) || strlen($code)<10 || !preg_match('/^0x[0-9a-f]+$/iD',$code)) throw new RuntimeException('Contrato no encontrado.');
 $protocol=json_decode(file_get_contents(__DIR__.'/../protocolo.json'),true,512,JSON_THROW_ON_ERROR);
 foreach (['registerSelector','getSelector','supersededSelector'] as $campo) {
  if (!str_contains(strtolower($code),substr($protocol[$campo],2))) throw new RuntimeException('Interfaz de contrato inesperada.');
 }
 $owner=rpcSepolia($config,'eth_call',[['to'=>$config['address'],'data'=>'0x8da5cb5b'],'latest']);
 if (!is_string($owner) || !preg_match('/^0x[0-9a-f]{64}$/iD',$owner)) throw new RuntimeException('Respuesta owner no válida.');
 echo json_encode(['red'=>'Sepolia','chainId'=>$chain,'contrato'=>$config['address'],'bytes_codigo'=>(strlen($code)-2)/2,'owner'=>'0x'.substr($owner,-40),'interfaz'=>'selectores esperados presentes','resultado'=>'lectura correcta; no se enviaron transacciones'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
} catch (Throwable $e) { fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1); }
