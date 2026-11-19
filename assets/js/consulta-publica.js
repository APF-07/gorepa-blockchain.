(() => {
    const source = document.getElementById('public-config');
    if (!source) return;
    const config = JSON.parse(source.textContent);
    const $ = id => document.getElementById(id);
    let busy = false;
    function clearResult() {
        $('public-result').className = 'public-proof public-proof-pending';
        $('public-title').textContent = 'CONSULTANDO BLOCKCHAIN…';
        $('public-description').textContent = 'Comprobando el recibo, las huellas y el registro del contrato en Sepolia.';
        for (const id of ['public-issuer','public-date','public-transaction','public-confirmations']) $(id).textContent = 'Pendiente de consulta';
        $('public-state').textContent = 'SIN COMPROBAR';
        $('public-explorer').hidden = true;
        $('public-explorer').removeAttribute('href');
    }
    function link(element, value, url) {
        const a = document.createElement('a');a.href = url;a.textContent = value;a.target = '_blank';a.rel = 'noopener noreferrer';element.replaceChildren(a);
    }
    async function query() {
        if (busy) return;
        busy = true;$('public-query').disabled = true;clearResult();
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 35000);
        try {
            const response = await fetch(config.endpoint, { cache: 'no-store', credentials: 'omit', signal: controller.signal, headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Consulta no disponible.');
            const data = await response.json();
            const accepted = ['verificado','reemplazado','pendiente','no_verificado','no_disponible'];
            const state = accepted.includes(data.estado) ? data.estado : 'no_disponible';
            const verified = state === 'verificado' || state === 'reemplazado';
            $('public-result').className = 'public-proof public-proof-' + state;
            $('public-title').textContent = (verified ? '✓ ' : '') + data.titulo;
            $('public-description').textContent = data.detalle;
            $('public-state').textContent = { verificado: 'ACTIVO', reemplazado: 'SUSTITUIDO', pendiente: 'PENDIENTE', no_verificado: 'NO VERIFICADO', no_disponible: 'NO DISPONIBLE' }[state];
            if (verified) {
                if (/^0x[a-f0-9]{40}$/i.test(data.emisor)) link($('public-issuer'), data.emisor, 'https://sepolia.etherscan.io/address/' + data.emisor);
                $('public-date').textContent = `${data.fecha} (hora de Lima; Unix: ${data.unix})`;
                $('public-confirmations').textContent = `${data.confirmaciones} · Bloque ${data.bloque}`;
                if (/^0x[a-f0-9]{64}$/i.test(data.tx)) {
                    const url = 'https://sepolia.etherscan.io/tx/' + data.tx;
                    link($('public-transaction'), data.tx, url);
                    $('public-explorer').href = url;$('public-explorer').hidden = false;
                }
            }
            $('public-feedback').textContent = verified ? 'Consulta realizada a Ethereum. No se enviaron transacciones.' : 'No se presenta como verificado sin una comprobación válida.';
        } catch {
            $('public-result').className = 'public-proof public-proof-no_disponible';
            $('public-title').textContent = 'CONSULTA TEMPORALMENTE NO DISPONIBLE';
            $('public-description').textContent = 'No fue posible comprobar Ethereum. Intenta nuevamente; no se concluye que el documento sea inválido.';
            $('public-state').textContent = 'NO DISPONIBLE';
            $('public-feedback').textContent = 'Pulsa Consultar Blockchain para reintentar.';
        } finally { clearTimeout(timeout);busy = false;$('public-query').disabled = false; }
    }
    async function copy(value) {
        try {
            if (navigator.clipboard && window.isSecureContext) await navigator.clipboard.writeText(value);
            else {
                const area = document.createElement('textarea');area.value = value;area.style.position = 'fixed';area.style.opacity = '0';document.body.append(area);area.select();
                const ok = document.execCommand('copy');area.remove();if (!ok) throw new Error('No disponible.');
            }
            $('public-feedback').textContent = 'Copiado al portapapeles.';
        } catch { $('public-feedback').textContent = 'No se pudo copiar automáticamente. Selecciona el texto o copia la dirección del navegador.'; }
    }
    $('public-query').addEventListener('click', query);
    $('public-copy-hash').addEventListener('click', () => copy(config.hash));
    $('public-copy-link').addEventListener('click', () => copy(config.url));
    query();
})();
