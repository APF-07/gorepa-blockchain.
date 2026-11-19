<?php
ob_start();
require_once __DIR__ . '/../includes/documentos.php';
requiereGestionDocumental();
require_once __DIR__ . '/../includes/blockchain.php';
function respuestaBlockchain(array $datos, int $status=200): void {
    while (ob_get_level()>0) ob_end_clean();
    http_response_code($status); header('Content-Type: application/json; charset=UTF-8'); header('Cache-Control: no-store');
    echo json_encode($datos,JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); exit;
}
if ($_SERVER['REQUEST_METHOD']!=='POST') { header('Allow: POST');respuestaBlockchain(['error'=>'Utiliza las acciones del módulo Blockchain.'],405); }
if (!csrfValido($_POST['csrf_token'] ?? null)) respuestaBlockchain(['error'=>'Solicitud no válida. Actualiza la página.'],403);
try {
    $id=filter_var($_POST['documento_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if (!$id) throw new DomainException('Documento no válido.');
    $stmt=$conexion->prepare('SELECT * FROM documentos WHERE id=?');$stmt->execute([$id]);$doc=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$doc) respuestaBlockchain(['error'=>'Documento no encontrado.'],404);
    $accion=is_string($_POST['accion'] ?? null)?$_POST['accion']:'';
    if ($accion==='preparar') {
        $anterior=is_string($_POST['codigo_anterior'] ?? null)?trim($_POST['codigo_anterior']):'';
        $previousId=str_repeat('0',64);
        if ($anterior!=='') {
            $stmt=$conexion->prepare('SELECT document_id FROM documentos WHERE codigo=?');$stmt->execute([$anterior]);$previousId=$stmt->fetchColumn();
            if (!$previousId || $previousId===$doc['document_id']) throw new DomainException('El código anterior debe corresponder a otro documento registrado.');
        }
        respuestaBlockchain(['documentId'=>'0x'.$doc['document_id'],'fileHash'=>'0x'.$doc['file_hash'],'metadataHash'=>'0x'.$doc['metadata_hash'],'previousId'=>'0x'.$previousId]);
    }
    if ($accion!=='confirmar') throw new DomainException('Operación no válida.');
    $txHash=is_string($_POST['tx_hash'] ?? null)?trim($_POST['tx_hash']):'';
    $config=configuracionBlockchain();
    $prueba=pruebaBlockchain($doc,$txHash,$config);
    guardarPruebaBlockchain($conexion,$doc,$prueba,$config,(int)$_SESSION['usuario_id']);
    respuestaBlockchain(['prueba'=>$prueba,'explorer'=>'https://sepolia.etherscan.io/tx/'.$prueba['txHash']]);
} catch (DomainException $e) { respuestaBlockchain(['error'=>$e->getMessage()],400); }
catch (Throwable $e) { respuestaBlockchain(['error'=>'No se pudo comprobar y guardar la transacción. Revisa la configuración o intenta nuevamente; el documento no se marcará como registrado sin confirmación.'],503); }
