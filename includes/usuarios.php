<?php
function textoUsuario(array $datos, string $campo): string {
    return isset($datos[$campo]) && is_string($datos[$campo]) ? trim($datos[$campo]) : '';
}
function administrarUsuarios(PDO $db, int $actor, callable $operacion): int {
    $bloqueo = $db->query("SELECT GET_LOCK('gorepa_administracion_usuarios', 5)");
    if ((int) $bloqueo->fetchColumn() !== 1) {
        throw new RuntimeException('Hay otra operación en curso. Intenta nuevamente.');
    }
    try {
        $db->beginTransaction();
        $usuarios = $db->query('SELECT id, rol, activo FROM usuarios ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
        $autorizado = false;
        foreach ($usuarios as $usuario) {
            if ((int) $usuario['id'] === $actor && $usuario['rol'] === 'administrador' && (int) $usuario['activo'] === 1) $autorizado = true;
        }
        if (!$autorizado) throw new DomainException('Tu cuenta ya no tiene permisos de administración.');
        $id = $operacion($usuarios);
        $db->commit();
        return $id;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    } finally {
        $db->query("SELECT RELEASE_LOCK('gorepa_administracion_usuarios')");
    }
}
function crearOperador(PDO $db, int $actor, array $datos): int {
    $nombre = textoUsuario($datos, 'nombre');
    $correo = strtolower(textoUsuario($datos, 'correo'));
    $password = isset($datos['password']) && is_string($datos['password']) ? $datos['password'] : '';
    $confirmacion = isset($datos['confirmacion']) && is_string($datos['confirmacion']) ? $datos['confirmacion'] : '';
    if ($nombre === '' || mb_strlen($nombre, 'UTF-8') > 120) throw new DomainException('El nombre debe tener entre 1 y 120 caracteres.');
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 150) throw new DomainException('Ingresa un correo válido de hasta 150 caracteres.');
    if (strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) throw new DomainException('La contraseña debe tener entre 12 y 72 bytes. Puedes usar una frase larga.');
    if (!hash_equals($password, $confirmacion)) throw new DomainException('Las contraseñas no coinciden.');
    $hash = password_hash($password, PASSWORD_DEFAULT);
    return administrarUsuarios($db, $actor, function () use ($db, $actor, $nombre, $correo, $hash): int {
        $stmt = $db->prepare('SELECT id FROM usuarios WHERE LOWER(correo) = LOWER(?) LIMIT 1');
        $stmt->execute([$correo]);
        if ($stmt->fetch()) throw new DomainException('Este correo ya está registrado.');
        $stmt = $db->prepare("INSERT INTO usuarios (nombre, correo, password_hash, rol, activo) VALUES (?, ?, ?, 'operador', 1)");
        $stmt->execute([$nombre, $correo, $hash]);
        $id = (int) $db->lastInsertId();
        $stmt = $db->prepare('INSERT INTO auditoria (usuario_id, accion, descripcion) VALUES (?, ?, ?)');
        $stmt->execute([$actor, 'CREACION_USUARIO', 'Usuario ID ' . $id . '; rol operador; activo 1']);
        return $id;
    });
}
function actualizarUsuario(PDO $db, int $actor, array $datos): int {
    $id = filter_var(textoUsuario($datos, 'usuario_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $rol = textoUsuario($datos, 'rol');
    $activo = textoUsuario($datos, 'activo');
    if (!$id || !in_array($rol, ['administrador', 'operador'], true) || !in_array($activo, ['0', '1'], true)) throw new DomainException('Los datos del usuario no son válidos.');
    return administrarUsuarios($db, $actor, function (array $usuarios) use ($db, $actor, $id, $rol, $activo): int {
        $anterior = null;
        $administradores = 0;
        foreach ($usuarios as $usuario) {
            if ((int) $usuario['id'] === $id) $anterior = $usuario;
            if ($usuario['rol'] === 'administrador' && (int) $usuario['activo'] === 1) $administradores++;
        }
        if (!$anterior) throw new DomainException('El usuario no existe.');
        if ($anterior['rol'] === 'administrador' && (int) $anterior['activo'] === 1 && ($rol !== 'administrador' || $activo !== '1') && $administradores <= 1) throw new DomainException('Debes conservar al menos un administrador activo.');
        if ($id === $actor) throw new DomainException('No puedes cambiar el rol ni el estado de tu propia cuenta.');
        if ($anterior['rol'] === $rol && (string) $anterior['activo'] === $activo) throw new DomainException('No hay cambios para guardar.');
        $stmt = $db->prepare('UPDATE usuarios SET rol = ?, activo = ? WHERE id = ?');
        $stmt->execute([$rol, (int) $activo, $id]);
        $stmt = $db->prepare('INSERT INTO auditoria (usuario_id, accion, descripcion) VALUES (?, ?, ?)');
        $stmt->execute([$actor, 'ACTUALIZACION_USUARIO', 'Usuario ID ' . $id . '; rol ' . $anterior['rol'] . ' -> ' . $rol . '; activo ' . $anterior['activo'] . ' -> ' . $activo]);
        return $id;
    });
}
