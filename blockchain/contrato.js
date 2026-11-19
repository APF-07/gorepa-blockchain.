(() => {
 'use strict';
 const element=document.getElementById('chain-config');
 if(!element) return;
 const config=JSON.parse(element.textContent);
 const $=id=>document.getElementById(id);
 const status=$('chain-status'),output=$('chain-output');
 const ids=['chain-connect','chain-register','chain-read','chain-verify','chain-confirm'];
 let provider,signer,contract,abi,busy=false;
 const storageKey=`gorepa:sepolia:${config.address}:${config.documentoId}`;
 let metaMask;
 const boundWallets=new WeakSet();
 const detectMetaMask=()=>window.ethereum?.providers?.find(item=>item.isMetaMask) ?? (window.ethereum?.isMetaMask?window.ethereum:null);
 try { $('chain-tx').value=localStorage.getItem(storageKey)??''; } catch {}
 function message(text,error=false){status.textContent=text;status.classList.toggle('chain-error',error);}
 function buttons(){
  const ready=Boolean(config.address && contract);
  $('chain-connect').disabled=busy || !config.address;
  for(const id of ['chain-register','chain-read','chain-verify']) $(id).disabled=busy || !ready;
  $('chain-confirm').disabled=busy || !config.address;
 }
 function describe(error){
  const names={Unauthorized:'La billetera no está autorizada en el contrato.',DuplicateDocument:'Este identificador ya existe en Ethereum. Consulta su registro o recupera su transacción.',DuplicateFile:'La huella de este PDF ya está registrada con otro identificador.',UnknownPreviousDocument:'El documento anterior no existe en el contrato.',AlreadySuperseded:'El documento anterior ya fue sustituido. Usa el registro más reciente de la cadena.',UnknownDocument:'El documento todavía no existe en este contrato de Sepolia.'};
  if(error.code==='ACTION_REJECTED' || error.code===4001)return 'Operación cancelada en la billetera. No se confirmó ningún registro nuevo en MySQL.';
  if(error.code===-32002 || error.info?.error?.code===-32002)return 'MetaMask ya tiene una solicitud pendiente. Abre el zorro en la barra de Chrome para aceptarla o cancelarla.';
  if(error.code==='TIMEOUT')return 'La confirmación tarda más de lo esperado. Conserva el hash y usa Comprobar y guardar confirmación.';
  if(error.code==='TRANSACTION_REPLACED')return 'La billetera reemplazó la transacción. Comprueba su nuevo hash en MetaMask y recupéralo aquí.';
  if(names[error.revert?.name])return names[error.revert.name];
  if(error.code==='INSUFFICIENT_FUNDS')return 'Falta ETH de prueba en Sepolia. Usa un faucet gratuito; no compres ETH real.';
  return error.message || 'No se pudo completar la operación.';
 }
 async function run(action){if(busy)return;busy=true;buttons();try{await action();}catch(error){message(describe(error),true);}finally{busy=false;buttons();}}
 async function network(){
  if(!provider)throw new Error('Primero conecta MetaMask.');
  const chain=await metaMask.request({method:'eth_chainId'});
  if(BigInt(chain)!==11155111n)throw new Error('Selecciona Ethereum Sepolia en MetaMask. Esta aplicación no permite registrar en otras redes.');
 }
 async function api(accion,extra={}){
  const response=await fetch('blockchain-api.php',{method:'POST',credentials:'same-origin',body:new URLSearchParams({accion,documento_id:String(config.documentoId),csrf_token:config.csrf,...extra}),headers:{Accept:'application/json'}});
  let result;try{result=await response.json();}catch{throw new Error('La sesión pudo finalizar. Actualiza la página e inicia sesión nuevamente.');}
  if(!response.ok)throw new Error(result.error??'No se pudo guardar la operación.');return result;
 }
 async function confirm(hash){
  if(!/^0x[0-9a-f]{64}$/i.test(hash))throw new Error('Introduce un hash de transacción válido.');
  const result=await api('confirmar',{tx_hash:hash});
  const proof=result.prueba;
  output.hidden=false;output.textContent=JSON.stringify({fuente:'Comprobación RPC del servidor en Sepolia',estado:proof.estado,confirmaciones:proof.confirmations,bloque:proof.block,transaccion:proof.txHash,explorador:result.explorer},null,2);
  if(proof.estado==='confirmada'){
   message('Transacción confirmada y guardada en MySQL. Actualiza el detalle para ver el estado documental.');
   try{localStorage.removeItem(storageKey);}catch{}
  }else if(proof.estado==='fallida')message('La transacción falló en Ethereum. Se guardó el intento fallido; el documento no se marcó como registrado.',true);
  else message(`Transacción pendiente: ${proof.confirmations} de ${config.confirmations} confirmaciones. Puedes volver a comprobarla con el mismo botón.`);
  return proof;
 }
 $('chain-connect').addEventListener('click',()=>run(async()=>{
  if(!window.ethers)throw new Error('No se pudo cargar la biblioteca ethers. Actualiza la página.');
  metaMask=detectMetaMask();
  if(!metaMask)throw new Error('MetaMask no está disponible en este perfil de Chrome. Abre esta misma dirección en la ventana donde usaste MetaMask con Remix. Si ya estás en ese perfil, permite el acceso de la extensión a localhost y recarga.');
  if(!boundWallets.has(metaMask)){metaMask.on?.('accountsChanged',reset);metaMask.on?.('chainChanged',reset);boundWallets.add(metaMask);}
  message('Abre MetaMask con el icono del zorro y acepta la solicitud de conexión.');
  await metaMask.request({method:'eth_requestAccounts'});
  if(BigInt(await metaMask.request({method:'eth_chainId'}))!==11155111n)await metaMask.request({method:'wallet_switchEthereumChain',params:[{chainId:'0xaa36a7'}]});
  provider=new ethers.BrowserProvider(metaMask);await network();signer=await provider.getSigner();
  const response=await fetch('/gorepa_blockchain/blockchain/abi.json',{cache:'no-cache'});
  if(!response.ok)throw new Error('No se pudo cargar el ABI del contrato.');abi=await response.json();
  const code=await provider.getCode(config.address);if(code==='0x')throw new Error('No existe contrato en la dirección configurada de Sepolia.');
  contract=new ethers.Contract(config.address,abi,signer);
  const address=await signer.getAddress();$('chain-wallet-label').textContent=address;
  const authorized=await contract.isOperator(address);
  message(authorized?'MetaMask conectada a Sepolia. La billetera está autorizada para registrar.':'MetaMask conectada. Puedes consultar; el dueño del contrato debe autorizar esta billetera para registrar.');
 }));
 $('chain-register').addEventListener('click',()=>run(async()=>{
  await network();
  if(!await contract.isOperator(await signer.getAddress()))throw new Error('La billetera no tiene autorización de operador en el contrato.');
  const data=await api('preparar',{codigo_anterior:$('chain-previous').value.trim()});
  const args=[data.documentId,data.fileHash,data.metadataHash,data.previousId];
  await contract.registerDocument.staticCall(...args);
  message('Revisa la operación en MetaMask. Se usarán únicamente fondos de prueba de Sepolia.');
  const tx=await contract.registerDocument(...args,{value:0n});
  $('chain-tx').value=tx.hash;try{localStorage.setItem(storageKey,tx.hash);}catch{}
  message('Transacción enviada. Conserva el hash mientras se confirma.');
  try{await confirm(tx.hash);}catch(error){message(`La transacción ya fue enviada. Conserva ${tx.hash}. ${describe(error)}`,true);}
  await tx.wait(config.confirmations,120000);
  await confirm(tx.hash);
 }));
 $('chain-read').addEventListener('click',()=>run(async()=>{
  await network();const record=await contract.getDocument(config.documentId);const next=await contract.supersededBy(config.documentId);
  const same=record.fileHash.toLowerCase()===config.fileHash.toLowerCase() && record.metadataHash.toLowerCase()===config.metadataHash.toLowerCase();
  output.hidden=false;output.textContent=JSON.stringify({fuente:'Contrato de Ethereum Sepolia mediante MetaMask',documentId:config.documentId,fileHash:record.fileHash,metadataHash:record.metadataHash,previousId:record.previousId,sustituidoPor:next,registradoPor:record.registrar,fecha:new Date(Number(record.timestamp)*1000).toISOString()},null,2);
  message(same?'La consulta a Ethereum coincide con las huellas de MySQL. Esta consulta no cambia el estado de la base de datos.':'Las huellas de Ethereum y MySQL no coinciden. Revisa el registro.',!same);
 }));
 $('chain-verify').addEventListener('click',()=>run(async()=>{
  await network();const file=$('chain-pdf').files[0];
  if(!file || file.size<=0 || file.size>5*1024*1024 || !/\.pdf$/i.test(file.name))throw new Error('Selecciona un PDF de hasta 5 MB.');
  const bytes=new Uint8Array(await file.arrayBuffer());
  if(new TextDecoder().decode(bytes.slice(0,5))!=='%PDF-')throw new Error('El archivo no tiene una cabecera PDF válida.');
  const hash=ethers.sha256(bytes);const record=await contract.getDocument(config.documentId);
  const same=hash.toLowerCase()===record.fileHash.toLowerCase();
  output.hidden=false;output.textContent=JSON.stringify({fuente:'Ethereum Sepolia (consulta directa mediante MetaMask)',hashDelPDF:hash,hashEnEthereum:record.fileHash,coincide:same},null,2);
  message(same?'El PDF coincide con la huella consultada en Ethereum. Esto no acredita autoría ni valida firmas digitales.':'El PDF es diferente al registro consultado en Ethereum.',!same);
 }));
 $('chain-confirm').addEventListener('click',()=>run(()=>confirm($('chain-tx').value.trim())));
 function reset(){contract=null;signer=null;if(provider)provider.destroy();provider=null;$('chain-wallet-label').textContent='Billetera o red cambiada. Vuelve a conectar.';buttons();}
 buttons();
 if(config.address && !detectMetaMask())message('MetaMask no está disponible en este perfil de Chrome. Abre el sistema en la misma ventana o perfil donde conectaste MetaMask con Remix.',true);
})();
