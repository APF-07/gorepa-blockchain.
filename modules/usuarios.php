<?php
require_once __DIR__ . '/../includes/auth.php';
requiereAdministrador();
require_once __DIR__ . '/../includes/usuarios.php';
require_once __DIR__ . '/../includes/vista.php';
$error = '';
$mensaje = $_SESSION['usuarios_mensaje'] ?? '';
unset($_SESSION['usuarios_mensaje']);
$nombre = '';
$correo = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = textoUsuario($_POST, 'nombre');
    $correo = textoUsuario($_POST, 'correo');
    try {
        if (!csrfValido($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            throw new DomainException('Solicitud no válida. Actualiza la página e inténtalo nuevamente.');
        }
        $accion = textoUsuario($_POST, 'accion');
        if ($accion === 'crear') {
            $id = crearOperador($conexion, (int) $_SESSION['usuario_id'], $_POST);
            $_SESSION['usuarios_mensaje'] = 'Operador creado correctamente. Usuario ID ' . $id . '.';
        } elseif ($accion === 'actualizar') {
            $id = actualizarUsuario($conexion, (int) $_SESSION['usuario_id'], $_POST);
            $_SESSION['usuarios_mensaje'] = 'Permisos y estado actualizados. Usuario ID ' . $id . '.';
        } else {
            throw new DomainException('Operación no válida.');
        }
        header('Location: /gorepa_blockchain/modules/usuarios.php', true, 303);
        exit;
    } catch (DomainException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        $error = 'No se pudo completar la operación. Intenta nuevamente más tarde.';
    }
}
$q = isset($_GET['q']) && is_string($_GET['q']) ? substr(trim($_GET['q']), 0, 150) : '';
$estado = isset($_GET['estado']) && is_string($_GET['estado']) && in_array($_GET['estado'], ['0', '1'], true) ? $_GET['estado'] : '';
$pagina = max(1, (int) (is_string($_GET['pagina'] ?? null) ? $_GET['pagina'] : 1));
$where = [];
$params = [];
if ($q !== '') { $where[] = '(nombre LIKE ? OR correo LIKE ?)'; $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; }
if ($estado !== '') { $where[] = 'activo = ?'; $params[] = (int) $estado; }
$filtro = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$stmt = $conexion->prepare('SELECT COUNT(*) FROM usuarios' . $filtro);
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$paginas = max(1, (int) ceil($total / 15));
$pagina = min($pagina, $paginas);
$offset = ($pagina - 1) * 15;
$stmt = $conexion->prepare('SELECT id, nombre, correo, rol, activo, creado_en FROM usuarios' . $filtro . ' ORDER BY id DESC LIMIT 15 OFFSET ' . $offset);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stats = $conexion->query("SELECT COUNT(*) AS total, COALESCE(SUM(activo=1),0) AS activos, COALESCE(SUM(rol='administrador' AND activo=1),0) AS administradores FROM usuarios")->fetch(PDO::FETCH_ASSOC);
$tituloPagina = 'Administración de usuarios';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-heading"><div><p class="eyebrow">CONTROL DE ACCESO</p><h1>Administración de usuarios</h1><p>Gestiona las cuentas y los permisos del prototipo. Cada cambio queda registrado en auditoría.</p></div><a class="action-btn action-primary" href="#crear-operador"><?= iconoUi('mas') ?><span>Nuevo operador</span></a></section>
<?php if ($mensaje): ?><p class="alerta-exito" role="status"><?= escapar($mensaje) ?></p><?php endif; ?>
<?php if ($error): ?><p class="error" role="alert"><?= escapar($error) ?></p><?php endif; ?>
<section class="stats-grid" aria-label="Estadísticas de usuarios">
<?php foreach (['total' => 'Cuentas registradas', 'activos' => 'Cuentas activas', 'administradores' => 'Administradores activos'] as $clave => $etiqueta): ?><article class="stat-card"><p><?= escapar($etiqueta) ?></p><strong><?= (int) $stats[$clave] ?></strong><span><?= $clave === 'administradores' ? 'Se conserva al menos un administrador activo' : 'Datos actuales de MySQL' ?></span></article><?php endforeach; ?>
</section>
<section class="dashboard-card">
<div class="section-heading"><div><h2>Usuarios del sistema</h2><p>Los cambios de estado y rol se aplican en la siguiente solicitud del usuario.</p></div></div>
<form method="get" class="filters" role="search" aria-label="Buscar usuarios"><div class="filter-search"><label for="q">Nombre o correo</label><input type="search" id="q" name="q" value="<?= escapar($q) ?>" maxlength="150" placeholder="Buscar usuario…"></div><div><label for="estado">Estado de la cuenta</label><select id="estado" name="estado"><option value="">Todos</option><option value="1" <?= $estado === '1' ? 'selected' : '' ?>>Activo</option><option value="0" <?= $estado === '0' ? 'selected' : '' ?>>Inactivo</option></select></div><div class="filter-actions"><button class="action-btn action-primary" type="submit"><?= iconoUi('buscar') ?><span>Buscar</span></button><a class="action-btn action-ghost" href="usuarios.php">Limpiar</a></div></form>
<p class="results-count"><?= $total ?> usuario(s) · Página <?= $pagina ?> de <?= $paginas ?></p>
<div class="tabla-contenedor"><table class="tabla-documentos user-table"><caption class="sr-only">Usuarios y permisos</caption><thead><tr><th scope="col">Usuario</th><th scope="col">Rol y estado</th><th scope="col">Fecha de creación</th><th scope="col">Administrar acceso</th></tr></thead><tbody>
<?php foreach ($usuarios as $usuario): $esPropio = (int) $usuario['id'] === (int) $_SESSION['usuario_id']; ?>
<tr><td><strong><?= escapar($usuario['nombre']) ?></strong><?php if ($esPropio): ?> <span class="self-label">Tu cuenta</span><?php endif; ?><span class="cell-secondary"><?= escapar($usuario['correo']) ?></span></td><td><span class="role-label"><?= escapar(ucfirst($usuario['rol'])) ?></span><span class="badge <?= (int) $usuario['activo'] === 1 ? 'estado-registrado' : 'estado-otro' ?>"><?= (int) $usuario['activo'] === 1 ? 'Activo' : 'Inactivo' ?></span></td><td><?= escapar($usuario['creado_en']) ?></td><td>
<?php if ($esPropio): ?><span class="texto-ayuda">Tu rol y estado están protegidos.</span><?php else: ?>
<form method="post" class="user-edit-form"><input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>"><input type="hidden" name="accion" value="actualizar"><input type="hidden" name="usuario_id" value="<?= (int) $usuario['id'] ?>">
<div><label class="sr-only" for="rol-<?= (int) $usuario['id'] ?>">Rol de <?= escapar($usuario['nombre']) ?></label><select id="rol-<?= (int) $usuario['id'] ?>" name="rol"><option value="operador" <?= $usuario['rol'] === 'operador' ? 'selected' : '' ?>>Operador</option><option value="administrador" <?= $usuario['rol'] === 'administrador' ? 'selected' : '' ?>>Administrador</option></select></div>
<div><label class="sr-only" for="activo-<?= (int) $usuario['id'] ?>">Estado de <?= escapar($usuario['nombre']) ?></label><select id="activo-<?= (int) $usuario['id'] ?>" name="activo"><option value="1" <?= (int) $usuario['activo'] === 1 ? 'selected' : '' ?>>Activo</option><option value="0" <?= (int) $usuario['activo'] === 0 ? 'selected' : '' ?>>Inactivo</option></select></div>
<button class="action-btn action-secondary action-compact" type="submit" aria-label="Guardar permisos de <?= escapar($usuario['nombre']) ?>">Guardar</button></form>
<?php endif; ?></td></tr>
<?php endforeach; ?>
<?php if (!$usuarios): ?><tr><td class="empty-state" colspan="4">No se encontraron usuarios con estos criterios.</td></tr><?php endif; ?>
</tbody></table></div>
<?php if ($paginas > 1): ?><nav class="pagination" aria-label="Páginas de usuarios"><?php foreach ([$pagina-1 => '← Anterior', $pagina+1 => 'Siguiente →'] as $numero => $etiqueta): if ($numero < 1 || $numero > $paginas) continue; ?><a class="action-btn action-secondary" href="?<?= escapar(http_build_query(['q'=>$q, 'estado'=>$estado, 'pagina'=>$numero])) ?>"><?= escapar($etiqueta) ?></a><?php endforeach; ?></nav><?php endif; ?>
</section>
<section id="crear-operador" class="dashboard-card create-user-card"><div class="section-heading"><div><p class="eyebrow">NUEVA CUENTA</p><h2>Crear operador</h2><p>La cuenta se crea activa con acceso a la gestión documental.</p></div></div>
<form method="post" class="create-user-form"><input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>"><input type="hidden" name="accion" value="crear">
<div><label for="nombre">Nombre completo</label><input id="nombre" name="nombre" maxlength="120" value="<?= escapar($nombre) ?>" autocomplete="off" required></div>
<div><label for="correo">Correo electrónico</label><input id="correo" name="correo" type="email" maxlength="150" value="<?= escapar($correo) ?>" autocomplete="off" required></div>
<div><label for="password">Contraseña inicial</label><input id="password" name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" aria-describedby="password-ayuda" required><p id="password-ayuda" class="texto-ayuda">Usa una frase larga: entre 12 y 72 bytes. Los caracteres con tildes pueden ocupar más de un byte.</p></div>
<div><label for="confirmacion">Repetir contraseña</label><input id="confirmacion" name="confirmacion" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></div>
<div class="create-user-actions"><p class="texto-ayuda">La contraseña se guarda con hash seguro y no se muestra en el listado ni en la auditoría.</p><button class="action-btn action-primary" type="submit"><?= iconoUi('mas') ?><span>Crear operador</span></button></div></form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
