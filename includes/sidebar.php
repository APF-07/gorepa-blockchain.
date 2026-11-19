<?php
require_once __DIR__ . '/auth.php';
requiereLogin();
$paginaActual = basename($_SERVER['SCRIPT_NAME'] ?? '');
if ($paginaActual === 'detalle.php') $paginaActual = 'documentos.php';
?>
<aside id="menu-lateral" class="sidebar">
<a class="brand" href="/gorepa_blockchain/modules/dashboard.php"><span class="brand-mark">G</span><span>GOREPA<strong>Blockchain</strong></span></a>
<p class="nav-label">ESPACIO DE TRABAJO</p>
<nav aria-label="Navegación principal">
<?php foreach (['dashboard.php' => 'Panel principal', 'documentos.php' => 'Documentos'] as $ruta => $etiqueta): ?>
<a href="/gorepa_blockchain/modules/<?= escapar($ruta) ?>" <?= $paginaActual === $ruta ? 'class="active" aria-current="page"' : '' ?>><?= escapar($etiqueta) ?></a>
<?php endforeach; ?>
<?php if (in_array($_SESSION['rol'] ?? '', ['administrador', 'operador'], true)): ?>
<a href="/gorepa_blockchain/modules/registrar.php" <?= $paginaActual === 'registrar.php' ? 'class="active" aria-current="page"' : '' ?>>Registrar documento</a>
<?php endif; ?>
<?php if (($_SESSION['rol'] ?? '') === 'administrador'): ?>
<a href="/gorepa_blockchain/modules/usuarios.php" <?= $paginaActual === 'usuarios.php' ? 'class="active" aria-current="page"' : '' ?>>Administrar usuarios</a>
<?php endif; ?>
<a href="/gorepa_blockchain/modules/verificar.php" <?= $paginaActual === 'verificar.php' ? 'class="active" aria-current="page"' : '' ?>>Verificar documento</a>
<?php if (($_SESSION['rol'] ?? '') === 'administrador'): ?>
<a href="/gorepa_blockchain/modules/auditoria.php" <?= $paginaActual === 'auditoria.php' ? 'class="active" aria-current="page"' : '' ?>>Auditoría del sistema</a>
<?php endif; ?>
<a href="/gorepa_blockchain/modules/blockchain.php" <?= $paginaActual === 'blockchain.php' ? 'class="active" aria-current="page"' : '' ?>>Registro Blockchain</a>
</nav>
<div class="sidebar-bottom"><p>Derecho Informático<br><strong>UNDAC · 2026</strong></p><form action="/gorepa_blockchain/logout.php" method="post" class="logout-form"><input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>"><button type="submit" class="logout-button"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5H5v14h4M14 8l4 4-4 4M9 12h9"/></svg><span>Cerrar sesión</span></button></form></div>
</aside>
