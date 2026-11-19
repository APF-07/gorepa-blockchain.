<?php
require_once __DIR__ . '/../includes/documentos.php';
requiereGestionDocumental();
require_once __DIR__ . '/../config/database.php';
function parametroFiltro(string $nombre): string {
    return isset($_GET[$nombre]) && is_string($_GET[$nombre]) ? trim($_GET[$nombre]) : '';
}
$q = substr(parametroFiltro('q'), 0, 255);
$tipo = parametroFiltro('tipo');
$estado = parametroFiltro('estado');
if (!in_array($tipo, tiposDocumentales(), true)) $tipo = '';
if (!in_array($estado, ['pendiente', 'registrado'], true)) $estado = '';
$pagina = max(1, (int) parametroFiltro('pagina'));
$porPagina = 15;
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(d.codigo LIKE ? OR d.asunto LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($tipo !== '') { $where[] = 'd.tipo = ?'; $params[] = $tipo; }
if ($estado !== '') { $where[] = 'd.estado = ?'; $params[] = $estado; }
$filtro = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$stmt = $conexion->prepare('SELECT COUNT(*) FROM documentos d' . $filtro);
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$paginas = max(1, (int) ceil($total / $porPagina));
$pagina = min($pagina, $paginas);
$offset = ($pagina - 1) * $porPagina;
$stmt = $conexion->prepare('SELECT d.id, d.codigo, d.tipo, d.asunto, d.estado, d.creado_en, u.nombre AS registrado_por FROM documentos d LEFT JOIN usuarios u ON d.usuario_id = u.id' . $filtro . ' ORDER BY d.id DESC LIMIT ' . $porPagina . ' OFFSET ' . $offset);
$stmt->execute($params);
$documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$tituloPagina = 'Documentos';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-heading"><div><p class="eyebrow">ARCHIVO DOCUMENTAL</p><h1>Documentos registrados</h1><p>Busca y consulta los documentos administrativos del prototipo.</p></div><a class="boton-enlace action-btn action-primary" href="registrar.php"><?= iconoUi('mas') ?><span>Registrar documento</span></a></section>
<section class="dashboard-card">
<form method="get" class="filters" role="search" aria-label="Buscar documentos">
<div class="filter-search"><label for="q">Código o asunto</label><input id="q" name="q" maxlength="255" value="<?= escapar($q) ?>" placeholder="Buscar documento…" type="search"></div>
<div><label for="tipo">Tipo</label><select id="tipo" name="tipo"><option value="">Todos los tipos</option><?php foreach (tiposDocumentales() as $opcion): ?><option value="<?= escapar($opcion) ?>" <?= $tipo === $opcion ? 'selected' : '' ?>><?= escapar($opcion) ?></option><?php endforeach; ?></select></div>
<div><label for="estado">Estado</label><select id="estado" name="estado"><option value="">Todos los estados</option><option value="pendiente" <?= $estado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option><option value="registrado" <?= $estado === 'registrado' ? 'selected' : '' ?>>Registrado</option></select></div>
<div class="filter-actions"><button class="action-btn action-primary" type="submit"><?= iconoUi('buscar') ?><span>Buscar</span></button><a class="action-btn action-ghost" href="documentos.php"><?= iconoUi('limpiar') ?><span>Limpiar</span></a></div>
</form>
<p class="results-count"><?= escapar($total) ?> documento(s) encontrado(s) · Página <?= escapar($pagina) ?> de <?= escapar($paginas) ?></p>
<div class="tabla-contenedor"><table class="tabla-documentos"><caption class="sr-only">Resultados de la búsqueda documental</caption><thead><tr><th scope="col">Documento</th><th scope="col">Asunto</th><th scope="col">Estado</th><th scope="col">Registrado por</th><th scope="col">Fecha de registro</th><th scope="col">Acciones</th></tr></thead><tbody>
<?php foreach ($documentos as $doc): ?><tr><td><strong><?= escapar($doc['codigo']) ?></strong><span class="cell-secondary"><?= escapar($doc['tipo']) ?></span></td><td><?= escapar($doc['asunto']) ?></td><td><span class="badge <?= claseEstado($doc['estado']) ?>"><?= escapar(ucfirst($doc['estado'])) ?></span></td><td><?= escapar($doc['registrado_por'] ?? 'Usuario no disponible') ?></td><td><?= escapar($doc['creado_en']) ?></td><td><a class="detail-link action-btn action-secondary action-compact" href="detalle.php?id=<?= (int) $doc['id'] ?>" aria-label="Ver detalle de <?= escapar($doc['codigo']) ?>"><?= iconoUi('ojo') ?><span>Ver detalle</span><?= iconoUi('flecha') ?></a></td></tr><?php endforeach; ?>
<?php if (!$documentos): ?><tr><td colspan="6" class="empty-state">No se encontraron documentos con estos criterios.</td></tr><?php endif; ?>
</tbody></table></div>
<?php if ($paginas > 1): ?><nav class="pagination" aria-label="Páginas de documentos">
<?php foreach ([$pagina - 1 => '← Anterior', $pagina + 1 => 'Siguiente →'] as $numero => $etiqueta): if ($numero < 1 || $numero > $paginas) continue; ?>
<a class="action-btn action-secondary" href="?<?= escapar(http_build_query(['q' => $q, 'tipo' => $tipo, 'estado' => $estado, 'pagina' => $numero])) ?>"><?= escapar($etiqueta) ?></a>
<?php endforeach; ?></nav><?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
