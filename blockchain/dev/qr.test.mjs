import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import qrcode from 'qrcode-generator';
import zxing from '@zxing/library';
const { QRCodeReader, BinaryBitmap, HybridBinarizer, RGBLuminanceSource } = zxing;
function decode(qr) {
    const size = qr.getModuleCount();
    const cell = 8, quiet = 4, width = (size + quiet * 2) * cell;
    const pixels = new Uint8ClampedArray(width * width).fill(255);
    for (let row=0; row<size; row++) for(let col=0; col<size; col++) {
        if (!qr.isDark(row,col)) continue;
        for(let y=0;y<cell;y++) for(let x=0;x<cell;x++) pixels[((row+quiet)*cell+y)*width+(col+quiet)*cell+x]=0;
    }
    return new QRCodeReader().decode(new BinaryBitmap(new HybridBinarizer(new RGBLuminanceSource(pixels,width,width)))).getText();
}
const hash='f36e05fafaac545f9e059ad09cc6bb93bbee6251c8caee984b09488a2028f327';
const publicUrl='http://192.168.1.20:8080/gorepa_blockchain/consulta-publica.php?documento='+'1'.repeat(64);
const transaction='https://sepolia.etherscan.io/tx/0xb8e19148e8b4dfdb8bf35396083926c7d51c8e1858b56350ce38f4a89d04a310';
for (const [label,value] of [['SHA-256 completo',hash],['Enlace Sepolia',transaction],['Ficha publica',publicUrl]]) {
    test('Lectura independiente del QR: '+label,()=>{
        const qr=qrcode(0,'H');qr.addData(value,'Byte');qr.make();
        assert.equal(decode(qr),value);
    });
}
test('Interfaz genera SVG y descargas; rechaza datos invalidos',async()=>{
    function card(kind,value) {
        const image={innerHTML:'',textContent:''};
        const download={disabled:true,addEventListener(name,handler){this[name]=handler;}};
        const status={textContent:''};
        return {dataset:{qrKind:kind,qrValue:value,qrFilename:'documento.svg'},image,download,status,querySelector(selector){return {'[data-qr-image]':image,'[data-qr-download]':download,'[data-qr-status]':status}[selector];}};
    }
    const cards=[card('hash',hash),card('transaction',transaction),card('public',publicUrl),card('hash','<script>'),card('transaction','https://otro.example/tx/1')];
    let saved;
    const context={window:{qrcode},Blob,URL:class extends URL {static createObjectURL(blob){saved=blob;return 'blob:prueba';} static revokeObjectURL(){}},setTimeout(fn){fn();},document:{querySelectorAll(){return cards;},createElement(){return {click(){},remove(){}};},body:{append(){}}}};
    vm.runInNewContext(fs.readFileSync(new URL('../../assets/js/documento-qr.js',import.meta.url),'utf8'),context);
    for(const item of cards.slice(0,3)){assert.equal(item.download.disabled,false);assert.match(item.image.innerHTML,/<svg/);}
    for(const item of cards.slice(3)){assert.equal(item.download.disabled,true);assert.match(item.image.textContent,/No se pudo generar/);}
    cards[0].download.click();assert.equal(saved.type,'image/svg+xml;charset=utf-8');assert.match(await saved.text(),/<svg/);
});
