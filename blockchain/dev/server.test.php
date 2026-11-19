<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../../includes/blockchain.php';
$f=json_decode(file_get_contents(__DIR__.'/fixture.json'),true,512,JSON_THROW_ON_ERROR);
function rpcFixture(array $f, array $overrides=[]): callable {
    return static function (string $method,array $params) use ($f,$overrides) {
        if(array_key_exists($method,$overrides))return $overrides[$method];
        return match($method) {
            'eth_chainId'=>'0xaa36a7','eth_getCode'=>$f['code'],
            'eth_getTransactionByHash'=>$f['tx'],'eth_getTransactionReceipt'=>$f['receipt'],
            'eth_getBlockByNumber'=>$f['block'],'eth_blockNumber'=>$f['latest'],
            'eth_call'=>str_starts_with($params[0]['data'],$f['protocol']['supersededSelector'])?'0x'.str_repeat('a',64):$f['call'],
            default=>throw new RuntimeException('RPC inesperado '.$method)
        };
    };
}
function ok(bool $value,string $label):void { if(!$value)throw new RuntimeException('FALLO: '.$label);echo 'OK: '.$label.PHP_EOL; }
function rejects(callable $fn,string $label):void { try{$fn();}catch(DomainException|RuntimeException $e){echo 'OK: '.$label.PHP_EOL;return;}throw new RuntimeException('FALLO: '.$label); }
$p=pruebaBlockchain($f['document'],$f['txHash'],$f['config'],rpcFixture($f));
ok($p['estado']==='confirmada' && $p['supersededBy']===str_repeat('a',64),'Recibo real de EVM, evento y registro confirman y detectan sustitución');
rejects(fn()=>pruebaBlockchain($f['document'],'0x123',$f['config'],rpcFixture($f)),'Hash de transacción inválido rechazado');
rejects(fn()=>pruebaBlockchain($f['document'],$f['txHash'],$f['config'],rpcFixture($f,['eth_chainId'=>'0x1'])),'Mainnet rechazada');
rejects(fn()=>pruebaBlockchain($f['document'],$f['txHash'],$f['config'],rpcFixture($f,['eth_getCode'=>'0x'])),'Dirección sin contrato rechazada');
$t=$f['tx'];$t['to']='0x'.str_repeat('f',40);
rejects(fn()=>pruebaBlockchain($f['document'],$f['txHash'],$f['config'],rpcFixture($f,['eth_getTransactionByHash'=>$t])),'Otro contrato rechazado');
$d=$f['document'];$d['file_hash']=str_repeat('f',64);
rejects(fn()=>pruebaBlockchain($d,$f['txHash'],$f['config'],rpcFixture($f)),'Hash documental distinto rechazado');
$t=$f['tx'];$t['value']='0x1';
rejects(fn()=>pruebaBlockchain($f['document'],$f['txHash'],$f['config'],rpcFixture($f,['eth_getTransactionByHash'=>$t])),'Transferencia de fondos rechazada');
$p=pruebaBlockchain($f['document'],$f['txHash'],$f['config'],rpcFixture($f,['eth_getTransactionReceipt'=>null]));ok($p['estado']==='pendiente','Sin recibo permanece pendiente');
$p=pruebaBlockchain($f['document'],$f['txHash'],$f['config'],rpcFixture($f,['eth_blockNumber'=>$f['receipt']['blockNumber']]));ok($p['estado']==='pendiente','Confirmaciones insuficientes no registran documento');
$r=$f['receipt'];$r['status']='0x0';$r['logs']=[];
$p=pruebaBlockchain($f['document'],$f['txHash'],$f['config'],rpcFixture($f,['eth_getTransactionReceipt'=>$r]));ok($p['estado']==='fallida','Recibo revertido devuelve fallida');
$r=$f['receipt'];$r['logs']=[];
rejects(fn()=>pruebaBlockchain($f['document'],$f['txHash'],$f['config'],rpcFixture($f,['eth_getTransactionReceipt'=>$r])),'Recibo exitoso sin evento rechazado');
$r=$f['receipt'];$r['logs'][0]['data']='0x'.str_repeat('f',192);
rejects(fn()=>pruebaBlockchain($f['document'],$f['txHash'],$f['config'],rpcFixture($f,['eth_getTransactionReceipt'=>$r])),'Evento falsificado rechazado');
$b=$f['block'];$b['hash']='0x'.str_repeat('f',64);
rejects(fn()=>pruebaBlockchain($f['document'],$f['txHash'],$f['config'],rpcFixture($f,['eth_getBlockByNumber'=>$b])),'Bloque no canónico rechazado');
rejects(fn()=>pruebaBlockchain($f['document'],$f['txHash'],$f['config'],rpcFixture($f,['eth_call'=>'0x'.str_repeat('0',384)])),'Lectura del contrato inconsistente rechazada');
echo 'Todas las comprobaciones del servidor pasaron sin conectar a MySQL ni Sepolia.',PHP_EOL;
