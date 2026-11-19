import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createHash } from 'node:crypto';
import solc from 'solc';
import { Interface, id } from 'ethers';
export const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
export function compile() {
 const source = fs.readFileSync(path.join(root,'blockchain/RegistroDocumentalGOREPA.sol'),'utf8');
 const input = {language:'Solidity',sources:{'RegistroDocumentalGOREPA.sol':{content:source}},settings:{optimizer:{enabled:true,runs:200},evmVersion:'paris',outputSelection:{'*':{'*':['abi','evm.bytecode.object','evm.deployedBytecode.object']}}}};
 const output=JSON.parse(solc.compile(JSON.stringify(input)));
 const errors=(output.errors??[]).filter(e=>e.severity==='error');
 if(errors.length) throw new Error(errors.map(e=>e.formattedMessage).join('\n'));
 return {...output.contracts['RegistroDocumentalGOREPA.sol'].RegistroDocumentalGOREPA,compiler:solc.version(),sourceHash:createHash('sha256').update(source).digest('hex')};
}
export function artifacts(contract) {
 const iface=new Interface(contract.abi);
 const protocol={compiler:contract.compiler,optimizerRuns:200,evmVersion:'paris',sourceHash:contract.sourceHash,registerSelector:iface.getFunction('registerDocument').selector,getSelector:iface.getFunction('getDocument').selector,supersededSelector:iface.getFunction('supersededBy').selector,eventTopic:id('DocumentRegistered(bytes32,bytes32,bytes32,bytes32,address,uint256)')};
 fs.writeFileSync(path.join(root,'blockchain/abi.json'),JSON.stringify(contract.abi,null,2)+'\n');
 fs.writeFileSync(path.join(root,'blockchain/protocolo.json'),JSON.stringify(protocol,null,2)+'\n');
 fs.mkdirSync(path.join(root,'assets/vendor'),{recursive:true});
 fs.copyFileSync(new URL('./node_modules/ethers/dist/ethers.umd.min.js',import.meta.url),path.join(root,'assets/vendor/ethers.umd.min.js'));
 return protocol;
}
if(process.argv[1]===fileURLToPath(import.meta.url)) { const contract=compile(); artifacts(contract);console.log('Compilación correcta: '+contract.compiler); }
