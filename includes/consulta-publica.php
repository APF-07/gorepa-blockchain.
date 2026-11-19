<?php
require_once __DIR__ . '/blockchain.php';
function esIpv4LocalQr(string $ip): bool {
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return false;
    $p = array_map('intval', explode('.', $ip));
    return $p[0] === 10 || ($p[0] === 192 && $p[1] === 168) || ($p[0] === 172 && $p[1] >= 16 && $p[1] <= 31);
}
function baseConsultaPublica(array $servidor): string {
    $base = getenv('GOREPA_PUBLIC_BASE_URL');
    if (is_string($base) && $base !== '') {
        $u = parse_url($base);
        if (!is_array($u) || !in_array($u['scheme'] ?? '', ['https','http'], true) || empty($u['host']) || isset($u['user']) || isset($u['pass']) || isset($u['query']) || isset($u['fragment'])) throw new RuntimeException('URL pública no válida.');
        return rtrim($base, '/') . '/';
    }
    $ip = $servidor['SERVER_ADDR'] ?? '';
    if (!esIpv4LocalQr($ip)) $ip = gethostbyname(gethostname());
    if (!esIpv4LocalQr($ip)) $ip = 'localhost';
    $port = (int)($servidor['SERVER_PORT'] ?? 8080);
    if ($port < 1 || $port > 65535) $port = 8080;
    $https = !empty($servidor['HTTPS']) && $servidor['HTTPS'] !== 'off';
    return ($https ? 'https://' : 'http://') . $ip . ((!$https && $port === 80) || ($https && $port === 443) ? '' : ':' . $port) . '/gorepa_blockchain/';
}
function urlConsultaPublica(string $documentId, array $servidor): string {
    if (!preg_match('/^[a-f0-9]{64}$/iD', $documentId)) throw new DomainException('Identificador no válido.');
    return baseConsultaPublica($servidor) . 'consulta-publica.php?documento=' . strtolower($documentId);
}
function resultadoPublicoBlockchain(array $doc, ?string $tx, array $config, ?callable $rpc = null): array {
    $base = ['estado'=>'pendiente','titulo'=>'REGISTRO SIN CONFIRMACIÓN PÚBLICA','detalle'=>'No hay una transacción confirmada disponible para comprobar este documento.','emisor'=>null,'fecha'=>null,'unix'=>null,'confirmaciones'=>null,'bloque'=>null,'tx'=>null,'sustituidoPor'=>null];
    if (!$tx || $config['address'] === '') return $base;
    $rpc ??= static function(string $m, array $p) use ($config) {
        try { return rpcSepolia($config, $m, $p); }
        catch (RuntimeException $e) {
            // Algunos RPC gratuitos no conservan estado histórico. Los registros de este
            // contrato no se editan: la lectura actual se contrasta igualmente con el evento.
            if ($m !== 'eth_call' || ($p[1] ?? 'latest') === 'latest') throw $e;
            return rpcSepolia($config, $m, [$p[0], 'latest']);
        }
    };
    try {
        $p = pruebaBlockchain($doc, $tx, $config, $rpc);
        if ($p['estado'] === 'fallida') return array_replace($base,['estado'=>'no_verificado','titulo'=>'TRANSACCIÓN FALLIDA','detalle'=>'La transacción no acredita el registro del documento.']);
        if ($p['estado'] !== 'confirmada') return array_replace($base,['detalle'=>'La red aún no aporta las confirmaciones requeridas. Intenta consultar nuevamente.']);
        $b = $rpc('eth_getBlockByNumber', ['0x'.dechex($p['block']), false]);
        $fecha = enteroHexBlockchain($b['timestamp'] ?? null);
        $reemplazado = $p['supersededBy'] !== str_repeat('0',64);
        return ['estado'=>$reemplazado?'reemplazado':'verificado','titulo'=>$reemplazado?'REGISTRO VERIFICADO · SUSTITUIDO':'DOCUMENTO VERIFICADO EN BLOCKCHAIN','detalle'=>'La huella y los metadatos de este registro coinciden con el contrato de Ethereum Sepolia. Esta consulta no compara un PDF que tengas en tu celular.','emisor'=>$p['wallet'],'fecha'=>(new DateTimeImmutable('@'.$fecha))->setTimezone(new DateTimeZone('America/Lima'))->format('d/m/Y H:i:s'),'unix'=>$fecha,'confirmaciones'=>$p['confirmations'],'bloque'=>$p['block'],'tx'=>$p['txHash'],'sustituidoPor'=>$reemplazado?'0x'.$p['supersededBy']:null];
    } catch (DomainException $e) {
        return array_replace($base,['estado'=>'no_verificado','titulo'=>'NO SE PUDO VALIDAR EL REGISTRO','detalle'=>'La evidencia recibida no permite confirmar la coincidencia del documento. No se muestra como verificado.']);
    } catch (Throwable $e) {
        return array_replace($base,['estado'=>'no_disponible','titulo'=>'CONSULTA TEMPORALMENTE NO DISPONIBLE','detalle'=>'No fue posible comprobar Ethereum en este momento. Intenta nuevamente; esto no demuestra que el archivo haya sido alterado.']);
    }
}
