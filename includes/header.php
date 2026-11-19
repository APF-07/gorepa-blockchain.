<?php
require_once __DIR__ . '/auth.php';
requiereLogin();
require_once __DIR__ . '/vista.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= escapar($tituloPagina ?? 'Panel principal') ?> · GOREPA Blockchain</title>
<link rel="stylesheet" href="/gorepa_blockchain/assets/css/estilos.css?v=<?= filemtime(__DIR__ . '/../assets/css/estilos.css') ?>">
<script src="/gorepa_blockchain/assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>" defer></script>
</head>
<body class="dashboard-body">
<a class="saltar" href="#contenido">Saltar al contenido</a>
<div class="app-layout">
<?php require __DIR__ . '/sidebar.php'; ?>
<div class="app-content">
<header class="topbar">
<button class="menu-toggle" type="button" aria-controls="menu-lateral" aria-expanded="false">☰ <span>Menú</span></button>
<span class="topbar-label">Registro y trazabilidad documental</span>
<div class="user-info"><strong><?= escapar($_SESSION['nombre'] ?? 'Usuario') ?></strong><span><?= escapar(ucfirst($_SESSION['rol'] ?? '')) ?></span></div>
</header>
<main id="contenido" class="dashboard-main">
<div class="academic-notice">Prototipo académico · UNDAC · No es un sistema oficial del Gobierno Regional de Pasco.</div>
