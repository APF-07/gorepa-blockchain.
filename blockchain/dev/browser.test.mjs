import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import ganache from 'ganache';
import * as ethers from 'ethers';
import {compile,root} from './compile.mjs';
import path from 'node:path';
test('Conexión y acciones de la interfaz con EVM real y DOM simulado',async()=>{
 const evm=ganache.provider({logging:{quiet:true},chain:{chainId:11155111,hardfork:'shanghai'}});
 const provider=new ethers.BrowserProvider(evm);provider.pollingInterval=50;
 try{
 const signer=await provider.getSigner();const c=compile();
 const contract=await new ethers.ContractFactory(c.abi,c.evm.bytecode.object,signer).deploy();await contract.waitForDeployment();
 const bytes=new TextEncoder().encode('%PDF-1.4\n% PDF ficticio prueba navegador\n%%EOF');
 const config={address:await contract.getAddress(),confirmations:2,documentoId:1,documentId:'0x'+'d'.repeat(64),fileHash:ethers.sha256(bytes),metadataHash:'0x'+'e'.repeat(64),csrf:'test'};
 const elements=new Map();
 const element=id=>{
  if(!elements.has(id))elements.set(id,{value:'',textContent:'',disabled:false,hidden:true,files:[],handlers:{},classList:{toggle(){}},addEventListener(name,fn){this.handlers[name]=fn;}});
  return elements.get(id);
 };
 element('chain-config').textContent=JSON.stringify(config);
 const metamask={isMetaMask:true,request:async request=>request.method==='eth_requestAccounts'?evm.request({method:'eth_accounts',params:[]}):evm.request(request),on(){}};
 const confirmations=[];
 const fetch=async(url,options)=>{
  if(String(url).includes('abi.json'))return {ok:true,json:async()=>c.abi};
  const action=options.body.get('accion');
  if(action==='preparar')return {ok:true,json:async()=>({...config,previousId:ethers.ZeroHash})};
  const hash=options.body.get('tx_hash');confirmations.push(hash);await evm.request({method:'evm_mine',params:[]});
  return {ok:true,json:async()=>({prueba:{estado:'confirmada',txHash:hash,confirmations:2,block:2},explorer:'https://sepolia.etherscan.io/tx/'+hash})};
 };
 const storage=new Map();
 const context={window:{ethers},ethers,document:{getElementById:element},fetch,localStorage:{getItem:k=>storage.get(k),setItem:(k,v)=>storage.set(k,v),removeItem:k=>storage.delete(k)},URLSearchParams,Uint8Array,TextDecoder,Date,console};
 vm.runInNewContext(fs.readFileSync(path.join(root,'blockchain/contrato.js'),'utf8'),context);
 assert.match(element('chain-status').textContent,/MetaMask no está disponible en este perfil/);
 await element('chain-connect').handlers.click();
 assert.match(element('chain-status').textContent,/misma dirección en la ventana/);
 assert.equal(element('chain-register').disabled,true);
 context.window.ethereum=metamask;
 await element('chain-connect').handlers.click();
 assert.match(element('chain-status').textContent,/autorizada/);
 assert.equal(element('chain-register').disabled,false);
 await element('chain-register').handlers.click();
 assert.equal((await contract.getDocument(config.documentId)).fileHash,config.fileHash);
 assert.equal(confirmations.length,2);
 assert.match(element('chain-status').textContent,/confirmada y guardada/);
 await element('chain-read').handlers.click();
 assert.match(element('chain-status').textContent,/coincide con las huellas/);
 element('chain-pdf').files=[{name:'original.pdf',size:bytes.length,arrayBuffer:async()=>bytes.buffer}];
 await element('chain-verify').handlers.click();assert.match(element('chain-status').textContent,/El PDF coincide/);
 const changed=new TextEncoder().encode('%PDF-1.4\n% Diferente\n%%EOF');
 element('chain-pdf').files=[{name:'otro.pdf',size:changed.length,arrayBuffer:async()=>changed.buffer}];
 await element('chain-verify').handlers.click();assert.match(element('chain-status').textContent,/El PDF es diferente/);
 await element('chain-confirm').handlers.click();assert.equal(confirmations.length,3);
 }finally{provider.destroy();await evm.disconnect();}
});
