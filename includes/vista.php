<?php
function escapar($valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function claseEstado($estado): string {
    return in_array($estado, ['pendiente', 'registrado'], true) ? 'estado-' . $estado : 'estado-otro';
}

function iconoUi(string $nombre): string {
    $trazos = [
        'ojo' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'lista' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'flecha' => '<path d="M5 12h14m-6-6 6 6-6 6"/>',
        'volver' => '<path d="M19 12H5m6-6-6 6 6 6"/>',
        'mas' => '<path d="M12 5v14M5 12h14"/>',
        'buscar' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/>',
        'limpiar' => '<path d="m7 7 10 10M17 7 7 17"/>',
        'descargar' => '<path d="M12 3v12m-5-5 5 5 5-5M5 16v5h14v-5"/>',
    ];
    return '<svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($trazos[$nombre] ?? '') . '</svg>';
}
