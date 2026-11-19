<?php
function configuracionBlockchain(): array {
    $red = json_decode(file_get_contents(__DIR__ . '/../blockchain/red.json'), true, 512, JSON_THROW_ON_ERROR);
    if (($red['chainId'] ?? null) !== 11155111) throw new RuntimeException('Solo se permite Sepolia.');
    $address = strtolower($red['contractAddress'] ?? '');
    if ($address !== '' && (!preg_match('/^0x[a-f0-9]{40}$/D', $address) || $address === '0x' . str_repeat('0',40))) throw new RuntimeException('Dirección de contrato no válida.');
    return ['address'=>$address, 'rpc'=>getenv('GOREPA_SEPOLIA_RPC_URL') ?: 'https://ethereum-sepolia-rpc.publicnode.com', 'confirmations'=>max(2,(int)($red['confirmations'] ?? 2))];
}
function rpcSepolia(array $config, string $method, array $params) {
    if (parse_url($config['rpc'], PHP_URL_SCHEME) !== 'https') throw new RuntimeException('Se requiere RPC HTTPS.');
    $c = curl_init($config['rpc']);
    curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode(['jsonrpc'=>'2.0','id'=>1,'method'=>$method,'params'=>$params],JSON_THROW_ON_ERROR)]);
    try {
        $body = curl_exec($c);
        if ($body === false || curl_getinfo($c,CURLINFO_HTTP_CODE) !== 200) throw new RuntimeException('RPC no disponible.');
        $json = json_decode($body,true,512,JSON_THROW_ON_ERROR);
        if (isset($json['error']) || !array_key_exists('result',$json)) throw new RuntimeException('Respuesta RPC no válida.');
        return $json['result'];
    } finally { curl_close($c); }
}
function enteroHexBlockchain($valor): int {
    if (!is_string($valor) || !preg_match('/^0x[0-9a-f]{1,13}$/iD',$valor)) throw new RuntimeException('Número RPC no válido.');
    return (int)hexdec(substr($valor,2));
}
function pruebaBlockchain(array $doc, string $txHash, array $config, ?callable $rpc = null): array {
    $txHash = strtolower($txHash);
    if (!preg_match('/^0x[a-f0-9]{64}$/D',$txHash)) throw new DomainException('El hash de transacción debe llevar 0x y 64 dígitos hexadecimales.');
    if ($config['address'] === '') throw new DomainException('Primero falta desplegar y configurar el contrato en Sepolia.');
    $rpc ??= static fn(string $m,array $p) => rpcSepolia($config,$m,$p);
    if (enteroHexBlockchain($rpc('eth_chainId',[])) !== 11155111) throw new DomainException('El servidor RPC no está conectado a Sepolia.');
    $code = $rpc('eth_getCode',[$config['address'],'latest']);
    if (!is_string($code) || !preg_match('/^0x[0-9a-f]+$/iD',$code) || $code === '0x0') throw new DomainException('No hay un contrato en la dirección configurada.');
    $protocol = json_decode(file_get_contents(__DIR__ . '/../blockchain/protocolo.json'),true,512,JSON_THROW_ON_ERROR);
    $tx = $rpc('eth_getTransactionByHash',[$txHash]);
    if (!is_array($tx)) throw new DomainException('La red todavía no encuentra la transacción. Conserva el hash y vuelve a consultar.');
    if (strtolower($tx['hash'] ?? '') !== $txHash || strtolower($tx['to'] ?? '') !== $config['address'] || !preg_match('/^0x[a-f0-9]{40}$/D',strtolower($tx['from'] ?? ''))) throw new DomainException('La transacción no corresponde al contrato configurado.');
    if (isset($tx['chainId']) && enteroHexBlockchain($tx['chainId']) !== 11155111) throw new DomainException('Transacción de otra red.');
    if (enteroHexBlockchain($tx['value'] ?? '0x0') !== 0) throw new DomainException('No se admiten transferencias de fondos.');
    $input = strtolower($tx['input'] ?? '');
    if (!preg_match('/^0x[a-f0-9]{264}$/D',$input) || substr($input,0,10) !== $protocol['registerSelector']) throw new DomainException('La transacción no ejecuta registerDocument.');
    $words = str_split(substr($input,10),64);
    foreach (['document_id','file_hash','metadata_hash'] as $index=>$campo) {
        if (!hash_equals(strtolower($doc[$campo]),$words[$index])) throw new DomainException('La transacción contiene identificadores o huellas de otro documento.');
    }
    $result = ['estado'=>'pendiente','txHash'=>$txHash,'wallet'=>strtolower($tx['from']),'block'=>null,'confirmations'=>0,'previousId'=>$words[3],'supersededBy'=>str_repeat('0',64)];
    $receipt = $rpc('eth_getTransactionReceipt',[$txHash]);
    if ($receipt === null) return $result;
    if (!is_array($receipt) || strtolower($receipt['transactionHash'] ?? '') !== $txHash || strtolower($receipt['to'] ?? '') !== $config['address'] || strtolower($receipt['from'] ?? '') !== $result['wallet']) throw new DomainException('Recibo de transacción no válido.');
    $blockNumber = enteroHexBlockchain($receipt['blockNumber'] ?? null);
    $block = $rpc('eth_getBlockByNumber',[$receipt['blockNumber'],false]);
    if (!is_array($block) || strtolower($block['hash'] ?? '') !== strtolower($receipt['blockHash'] ?? '') || !preg_match('/^0x[a-f0-9]{64}$/iD',$block['hash'] ?? '')) throw new DomainException('El bloque del recibo no pertenece a la cadena actual.');
    $latest = enteroHexBlockchain($rpc('eth_blockNumber',[]));
    if ($latest < $blockNumber) throw new RuntimeException('RPC inconsistente.');
    $result['block'] = $blockNumber;
    $result['confirmations'] = $latest-$blockNumber+1;
    $status = enteroHexBlockchain($receipt['status'] ?? null);
    if ($status === 0) { $result['estado']='fallida'; return $result; }
    if ($status !== 1) throw new DomainException('Estado de recibo desconocido.');
    $matched = false;
    foreach (($receipt['logs'] ?? []) as $log) {
        $topics = array_map('strtolower',$log['topics'] ?? []);
        if (strtolower($log['address'] ?? '') !== $config['address'] || ($topics[0] ?? '') !== $protocol['eventTopic']) continue;
        $expectedTopics = [$protocol['eventTopic'],'0x'.$words[0],'0x'.$words[3],'0x'.str_pad(substr($result['wallet'],2),64,'0',STR_PAD_LEFT)];
        $data = strtolower($log['data'] ?? '');
        if ($topics !== $expectedTopics || !preg_match('/^0x[a-f0-9]{192}$/D',$data) || substr($data,2,128) !== $words[1].$words[2] || ($log['removed'] ?? false)) throw new DomainException('El evento del contrato no coincide con el documento.');
        $matched = true;
    }
    if (!$matched) throw new DomainException('El recibo no contiene el evento de registro esperado.');
    $stored = strtolower((string)$rpc('eth_call',[['to'=>$config['address'],'data'=>$protocol['getSelector'].$words[0]],$receipt['blockNumber']]));
    if (!preg_match('/^0x[a-f0-9]{384}$/D',$stored)) throw new DomainException('No se pudo comprobar el registro del contrato.');
    $record = str_split(substr($stored,2),64);
    if ($record[0] !== $words[1] || $record[1] !== $words[2] || $record[2] !== $words[3] || $record[3] !== str_pad(substr($result['wallet'],2),64,'0',STR_PAD_LEFT) || $record[5] !== str_pad('1',64,'0',STR_PAD_LEFT)) throw new DomainException('Los datos consultados en Ethereum no coinciden.');
    $superseded = strtolower((string)$rpc('eth_call',[['to'=>$config['address'],'data'=>$protocol['supersededSelector'].$words[0]],'latest']));
    if (!preg_match('/^0x[a-f0-9]{64}$/D',$superseded)) throw new RuntimeException('Respuesta de historial no válida.');
    $result['supersededBy']=substr($superseded,2);
    $canonical = $rpc('eth_getBlockByNumber',[$receipt['blockNumber'],false]);
    if (($canonical['hash'] ?? null) !== $block['hash']) throw new DomainException('La cadena cambió durante la consulta. Reintenta.');
    if ($result['confirmations'] >= $config['confirmations']) $result['estado']='confirmada';
    return $result;
}
function guardarPruebaBlockchain(PDO $db, array $doc, array $prueba, array $config, int $actor): void {
    $db->beginTransaction();
    try {
        $stmt=$db->prepare('SELECT document_id,file_hash,metadata_hash FROM documentos WHERE id=? FOR UPDATE');$stmt->execute([$doc['id']]);
        $actual=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$actual || $actual['document_id']!==$doc['document_id'] || $actual['file_hash']!==$doc['file_hash'] || $actual['metadata_hash']!==$doc['metadata_hash']) throw new DomainException('El registro documental cambió durante la comprobación.');
        $stmt=$db->prepare('SELECT * FROM transacciones WHERE tx_hash=? FOR UPDATE');$stmt->execute([$prueba['txHash']]);$previa=$stmt->fetch(PDO::FETCH_ASSOC);
        if ($previa && ((int)$previa['documento_id'] !== (int)$doc['id'] || strtolower($previa['contract_address']) !== $config['address'] || $previa['network']!=='sepolia')) throw new DomainException('La transacción ya está asociada a otro registro.');
        if ($previa && $previa['estado']==='confirmada' && $prueba['estado']!=='confirmada') throw new DomainException('La red ya no confirma un registro antes confirmado. Se requiere revisar su historial.');
        if ($previa) {
            $stmt=$db->prepare('UPDATE transacciones SET estado=?,block_number=? WHERE id=?');$stmt->execute([$prueba['estado'],$prueba['block'],$previa['id']]);
        } else {
            $stmt=$db->prepare('INSERT INTO transacciones (documento_id,tx_hash,contract_address,wallet_address,network,block_number,estado) VALUES (?,?,?,?,?,?,?)');$stmt->execute([$doc['id'],$prueba['txHash'],$config['address'],$prueba['wallet'],'sepolia',$prueba['block'],$prueba['estado']]);
        }
        if ($prueba['estado']==='confirmada') {
            $estadoDoc=$prueba['supersededBy']===str_repeat('0',64)?'registrado':'reemplazado';
            $stmt=$db->prepare('UPDATE documentos SET estado=? WHERE id=?');$stmt->execute([$estadoDoc,$doc['id']]);
            if ($prueba['previousId']!==str_repeat('0',64)) {
                $stmt=$db->prepare("UPDATE documentos SET estado='reemplazado' WHERE document_id=? AND estado='registrado'");$stmt->execute([$prueba['previousId']]);
            }
        }
        if (!$previa || $previa['estado']!==$prueba['estado']) {
            $stmt=$db->prepare('INSERT INTO auditoria (usuario_id,accion,descripcion) VALUES (?,?,?)');
            $stmt->execute([$actor,'BLOCKCHAIN_'.strtoupper($prueba['estado']),json_encode(['documento_id'=>(int)$doc['id'],'tx_hash'=>$prueba['txHash'],'contrato'=>$config['address'],'red'=>'sepolia','bloque'=>$prueba['block'],'confirmaciones'=>$prueba['confirmations'],'anterior_id'=>$prueba['previousId']],JSON_THROW_ON_ERROR)]);
        }
        $db->commit();
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
}
