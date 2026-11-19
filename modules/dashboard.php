<?php
require_once __DIR__ . '/../includes/auth.php';
requiereLogin();
require_once __DIR__ . '/../config/database.php';
$estadisticas = null;
$ultimos = [];
$errorDatos = false;
try {
    $estadisticas = $conexion->query("SELECT COUNT(*) AS total, COALESCE(SUM(estado = 'pendiente'), 0) AS pendientes, COALESCE(SUM(estado = 'registrado'), 0) AS registrados FROM documentos")->fetch(PDO::FETCH_ASSOC);
    $ultimos = $conexion->query('SELECT id, codigo, tipo, asunto, estado, creado_en FROM documentos ORDER BY id DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorDatos = true;
    $estadisticas = null;
}
$tituloPagina = 'Panel principal';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-heading"><div><p class="eyebrow">VISTA GENERAL</p><h1>Panel principal</h1><p>Bienvenido, <?= escapar($_SESSION['nombre'] ?? 'Usuario') ?>. Consulta la actividad documental del prototipo.</p></div>
<?php if (in_array($_SESSION['rol'] ?? '', ['administrador', 'operador'], true)): ?><a class="boton-enlace action-btn action-primary" href="registrar.php"><?= iconoUi('mas') ?><span>Registrar documento</span></a><?php endif; ?></section>
<?php if ($errorDatos): ?><p class="error" role="alert">No se pudieron consultar los datos. Intenta nuevamente más tarde.</p><?php endif; ?>
<section class="stats-grid" aria-label="Estadísticas documentales">
<?php foreach ([['total', 'Documentos', 'Total almacenado en MySQL'], ['pendientes', 'Pendientes', 'Pendientes de registro Blockchain'], ['registrados', 'Estado registrado', 'Según el estado almacenado en MySQL']] as [$clave, $etiqueta, $ayuda]): ?>
<article class="stat-card"><p><?= escapar($etiqueta) ?></p><strong><?= $estadisticas !== null ? escapar($estadisticas[$clave]) : '—' ?></strong><span><?= escapar($ayuda) ?></span></article>
<?php endforeach; ?>
</section>
<section class="dashboard-card"><div class="section-heading"><div><h2>Últimos documentos</h2><p>Los cinco registros más recientes.</p></div><a class="action-btn action-secondary" href="documentos.php"><?= iconoUi('lista') ?><span>Ver todos</span><?= iconoUi('flecha') ?></a></div>
<div class="tabla-contenedor"><table class="tabla-documentos"><caption class="sr-only">Últimos documentos registrados</caption><thead><tr><th scope="col">Documento</th><th scope="col">Asunto</th><th scope="col">Estado</th><th scope="col">Fecha de registro</th></tr></thead><tbody>
<?php foreach ($ultimos as $doc): ?><tr><td><a class="document-code-link" href="detalle.php?id=<?= (int) $doc['id'] ?>"><strong><?= escapar($doc['codigo']) ?></strong></a><span class="cell-secondary"><?= escapar($doc['tipo']) ?></span></td><td><?= escapar($doc['asunto']) ?></td><td><span class="badge <?= claseEstado($doc['estado']) ?>"><?= escapar(ucfirst($doc['estado'])) ?></span></td><td><?= escapar($doc['creado_en']) ?></td></tr><?php endforeach; ?>
<?php if (!$ultimos): ?><tr><td colspan="4" class="empty-state"><?= $errorDatos ? 'Datos temporalmente no disponibles.' : 'Todavía no hay documentos registrados.' ?></td></tr><?php endif; ?>
</tbody></table></div></section>
<section class="info-grid"><article class="dashboard-card"><p class="eyebrow">OFF-CHAIN</p><h2>Registro documental</h2><p>Los PDF se almacenan de forma protegida. MySQL conserva sus metadatos y el hash SHA-256 generado durante el registro.</p></article><article class="dashboard-card"><p class="eyebrow">ON-CHAIN · SEPOLIA</p><h2>Integración con Ethereum</h2><a class="action-btn action-secondary action-compact" href="blockchain.php">Abrir módulo Blockchain →</a><p>El módulo de conexión está preparado. El registro en Ethereum requiere un contrato desplegado en Sepolia y una transacción confirmada. Los indicadores de este panel provienen de MySQL.</p></article></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
