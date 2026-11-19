(() => {
    for (const card of document.querySelectorAll('[data-document-qr]')) {
        const image = card.querySelector('[data-qr-image]');
        const download = card.querySelector('[data-qr-download]');
        const status = card.querySelector('[data-qr-status]');
        try {
            const value = card.dataset.qrValue;
            let publicLink = false;
            if (card.dataset.qrKind === 'public') {
                const url = new URL(value);
                publicLink = ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password && !url.hash
                    && url.pathname.endsWith('/gorepa_blockchain/consulta-publica.php')
                    && /^[a-f0-9]{64}$/i.test(url.searchParams.get('documento') || '')
                    && [...url.searchParams.keys()].length === 1;
            }
            const valid = publicLink || (card.dataset.qrKind === 'hash'
                ? /^[a-f0-9]{64}$/i.test(value)
                : card.dataset.qrKind === 'transaction' && /^https:\/\/sepolia\.etherscan\.io\/tx\/0x[a-f0-9]{64}$/i.test(value));
            if (!valid) throw new Error('Referencia no válida.');
            if (typeof window.qrcode !== 'function') throw new Error('Biblioteca QR no disponible.');
            const qr = window.qrcode(0, 'H');
            qr.addData(value, 'Byte');
            qr.make();
            const svg = qr.createSvgTag(5, 20);
            // El SVG procede exclusivamente del generador local; no contiene HTML del usuario.
            image.innerHTML = svg;
            download.disabled = false;
            download.addEventListener('click', () => {
                try {
                    const file = new Blob([svg], { type: 'image/svg+xml;charset=utf-8' });
                    const url = URL.createObjectURL(file);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = card.dataset.qrFilename;
                    document.body.append(link);
                    link.click();
                    link.remove();
                    setTimeout(() => URL.revokeObjectURL(url), 1000);
                    status.textContent = 'QR preparado para descargar en formato SVG.';
                } catch {
                    status.textContent = 'No se pudo descargar el QR. Puedes consultar su referencia como texto.';
                }
            });
        } catch {
            image.textContent = 'No se pudo generar el QR. Actualiza la página o consulta la referencia como texto.';
            download.disabled = true;
        }
    }
})();
