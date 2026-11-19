<?php
require_once __DIR__ . '/../includes/auth.php';
requiereAdministrador();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit('La auditoría es una vista de consulta.');
}
require_once __DIR__ . '/../includes/auditoria.php';
$error = '';
$filtros = ['q'=>'','accion'=>'','usuario'=>'','desde'=>'','hasta'=>''];
$stats = ['total'=>0,'usuarios'=>0,'verificaciones'=>0];
$registros = $acciones = $usuarios = [];
$pagina = max(1, (int) (is_string($_GET['pagina'] ?? null) ? $_GET['pagina'] : 1));
$paginas = 1;
try {
    $consulta = filtrosAuditoria($_GET);
    $filtros = $consulta['valores'];
    $join = ' FROM auditoria a LEFT JOIN usuarios u ON u.id = a.usuario_id';
    $stmt = $conexion->prepare("SELECT COUNT(*) AS total, COUNT(DISTINCT a.usuario_id) AS usuarios, COALESCE(SUM(a.accion='VERIFICACION_DOCUMENTO'),0) AS verificaciones" . $join . $consulta['where']);
    $stmt->execute($consulta['params']);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $paginas = max(1, (int)ceil((int)$stats['total'] / 20));
    $pagina = min($pagina, $paginas);
    $stmt = $conexion->prepare('SELECT a.id, a.usuario_id, a.accion, a.descripcion, a.creado_en, u.nombre AS usuario_nombre' . $join . $consulta['where'] . ' ORDER BY a.id DESC LIMIT 20 OFFSET ' . (($pagina - 1) * 20));
    $stmt->execute($consulta['params']);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $acciones = $conexion->query('SELECT DISTINCT accion FROM auditoria ORDER BY accion')->fetchAll(PDO::FETCH_COLUMN);
    $usuarios = $conexion->query('SELECT id, nombre, activo FROM usuarios ORDER BY nombre, id')->fetchAll(PDO::FETCH_ASSOC);
} catch (DomainException $e) {
    http_response_code(400);
    $error = $e->getMessage();
} catch (PDOException $e) {
    http_response_code(503);
    $error = 'No se pudo consultar la auditoría. Intenta nuevamente más tarde.';
}
$tituloPagina = 'Auditoría del sistema';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-heading"><div><p class="eyebrow">TRAZABILIDAD · ACCESO ADMINISTRATIVO</p><h1>Auditoría del sistema</h1><p>Consulta quién realizó cada operación, qué ocurrió y cuándo quedó registrada.</p></div><a class="action-btn action-secondary" href="auditoria.php"><?= iconoUi('lista') ?><span>Actualizar historial</span></a></section>
<div class="academic-notice">Historial de la aplicación guardado en MySQL. Esta pantalla es de consulta y no permite modificar ni eliminar eventos. No es un registro inmutable en Blockchain.</div>
<?php if ($error): ?><p class="error" role="alert"><?= escapar($error) ?></p><p><a class="action-btn action-secondary" href="auditoria.php">Restablecer filtros</a></p><?php endif; ?>
<?php if (!$error): ?>
<section class="stats-grid" aria-label="Resumen de los resultados filtrados">
<?php foreach (['total'=>'Operaciones encontradas','usuarios'=>'Usuarios con operaciones','verificaciones'=>'Verificaciones documentales'] as $campo=>$etiqueta): ?><article class="stat-card"><p><?= escapar($etiqueta) ?></p><strong><?= (int)$stats[$campo] ?></strong><span>Según los filtros seleccionados</span></article><?php endforeach; ?>
</section>
<section class="dashboard-card">
<form class="filters audit-filters" method="get" role="search" aria-label="Filtrar auditoría">
<div class="filter-search"><label for="q">Buscar en el historial</label><input type="search" id="q" name="q" value="<?= escapar($filtros['q']) ?>" maxlength="150" placeholder="Usuario, acción o descripción…"></div>
<div><label for="accion">Operación</label><select id="accion" name="accion"><option value="">Todas las operaciones</option><?php foreach ($acciones as $accion): ?><option value="<?= escapar($accion) ?>" <?= $filtros['accion'] === $accion ? 'selected' : '' ?>><?= escapar(etiquetaAccionAuditoria($accion)) ?></option><?php endforeach; ?></select></div>
<div><label for="usuario">Usuario</label><select id="usuario" name="usuario"><option value="">Todos los usuarios</option><option value="sistema" <?= $filtros['usuario'] === 'sistema' ? 'selected' : '' ?>>Sin usuario asociado</option><?php foreach ($usuarios as $usuario): ?><option value="<?= (int)$usuario['id'] ?>" <?= $filtros['usuario'] === (string)$usuario['id'] ? 'selected' : '' ?>><?= escapar($usuario['nombre']) ?><?= (int)$usuario['activo'] === 0 ? ' · Inactivo' : '' ?></option><?php endforeach; ?></select></div>
<div><label for="desde">Desde</label><input type="date" id="desde" name="desde" value="<?= escapar($filtros['desde']) ?>"></div>
<div><label for="hasta">Hasta</label><input type="date" id="hasta" name="hasta" value="<?= escapar($filtros['hasta']) ?>"></div>
<div class="filter-actions"><button class="action-btn action-primary" type="submit"><?= iconoUi('buscar') ?><span>Filtrar</span></button><a class="action-btn action-ghost" href="auditoria.php">Limpiar</a></div>
</form>
<p class="results-count"><?= (int)$stats['total'] ?> evento(s) · Página <?= $pagina ?> de <?= $paginas ?> · Se muestran hasta 20 por página. Fechas según el registro de MySQL.</p>
<div class="tabla-contenedor"><table class="tabla-documentos audit-table"><caption class="sr-only">Operaciones registradas en auditoría</caption><thead><tr><th scope="col">Evento</th><th scope="col">Usuario</th><th scope="col">Operación</th><th scope="col">Detalle</th><th scope="col">Fecha y hora</th></tr></thead><tbody>
<?php foreach ($registros as $registro): $json = json_decode($registro['descripcion'] ?? '', true); ?>
<tr><td><span class="audit-event-id">#<?= (int)$registro['id'] ?></span></td><td><strong><?= escapar($registro['usuario_nombre'] ?? ($registro['usuario_id'] === null ? 'Sin usuario asociado' : 'Usuario no disponible')) ?></strong><?php if ($registro['usuario_id'] !== null): ?><span class="cell-secondary">Usuario ID <?= (int)$registro['usuario_id'] ?></span><?php endif; ?></td><td><span class="audit-action <?= $registro['accion'] === 'INTEGRIDAD_DOCUMENTO_FALLIDA' ? 'audit-action-warning' : '' ?>"><?= escapar(etiquetaAccionAuditoria($registro['accion'])) ?></span></td><td class="audit-description"><p><?= escapar(resumenAuditoria($registro['descripcion'])) ?></p>
<?php if (is_array($json)): ?><details class="audit-technical"><summary>Información técnica</summary><pre><?= escapar(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)) ?></pre></details><?php endif; ?>
<?php if (preg_match('/^Documento ID (\d+)$/D', $registro['descripcion'] ?? '', $referencia)): ?><a class="action-btn action-secondary action-compact" href="detalle.php?id=<?= (int)$referencia[1] ?>"><?= iconoUi('ojo') ?><span>Ver documento</span></a><?php endif; ?>
</td><td><time><?= escapar($registro['creado_en']) ?></time></td></tr>
<?php endforeach; ?>
<?php if (!$registros): ?><tr><td colspan="5" class="empty-state">No hay operaciones que coincidan con estos filtros.</td></tr><?php endif; ?>
</tbody></table></div>
<?php if ($paginas > 1): ?><nav class="pagination" aria-label="Páginas de auditoría"><?php foreach ([$pagina-1=>'← Anterior',$pagina+1=>'Siguiente →'] as $numero=>$etiqueta): if ($numero < 1 || $numero > $paginas) continue; ?><a class="action-btn action-secondary" href="?<?= escapar(http_build_query(array_merge($filtros,['pagina'=>$numero]))) ?>"><?= escapar($etiqueta) ?></a><?php endforeach; ?></nav><?php endif; ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
