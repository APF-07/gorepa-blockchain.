<?php
function filtrosAuditoria(array $entrada): array {
    $texto = static function (string $campo) use ($entrada): string {
        return isset($entrada[$campo]) && is_string($entrada[$campo]) ? trim($entrada[$campo]) : '';
    };
    $filtros = ['q'=>mb_substr($texto('q'), 0, 150, 'UTF-8'), 'accion'=>mb_substr($texto('accion'), 0, 100, 'UTF-8'), 'usuario'=>$texto('usuario'), 'desde'=>$texto('desde'), 'hasta'=>$texto('hasta')];
    foreach (['desde','hasta'] as $campo) {
        $fecha = $filtros[$campo];
        if ($fecha !== '') {
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $fecha, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) throw new DomainException('Selecciona fechas válidas.');
        }
    }
    if ($filtros['desde'] !== '' && $filtros['hasta'] !== '' && $filtros['desde'] > $filtros['hasta']) throw new DomainException('La fecha inicial no puede ser posterior a la fecha final.');
    if ($filtros['usuario'] !== '' && $filtros['usuario'] !== 'sistema' && !filter_var($filtros['usuario'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]])) throw new DomainException('El usuario seleccionado no es válido.');
    $where = [];
    $params = [];
    if ($filtros['q'] !== '') {
        $where[] = '(a.descripcion LIKE ? OR a.accion LIKE ? OR u.nombre LIKE ?)';
        $params = array_fill(0, 3, '%' . $filtros['q'] . '%');
    }
    if ($filtros['accion'] !== '') { $where[] = 'a.accion = ?'; $params[] = $filtros['accion']; }
    if ($filtros['usuario'] === 'sistema') $where[] = 'a.usuario_id IS NULL';
    elseif ($filtros['usuario'] !== '') { $where[] = 'a.usuario_id = ?'; $params[] = (int)$filtros['usuario']; }
    if ($filtros['desde'] !== '') { $where[] = 'a.creado_en >= ?'; $params[] = $filtros['desde'] . ' 00:00:00'; }
    if ($filtros['hasta'] !== '') { $where[] = 'a.creado_en <= ?'; $params[] = $filtros['hasta'] . ' 23:59:59'; }
    return ['valores'=>$filtros, 'where'=>$where ? ' WHERE ' . implode(' AND ', $where) : '', 'params'=>$params];
}
function etiquetaAccionAuditoria(string $accion): string {
    return [
        'REGISTRO_DOCUMENTO'=>'Registro de documento',
        'BLOCKCHAIN_PENDIENTE'=>'Transacción Blockchain pendiente',
        'BLOCKCHAIN_CONFIRMADA'=>'Registro confirmado en Sepolia',
        'BLOCKCHAIN_FALLIDA'=>'Transacción Blockchain fallida',
        'DESCARGA_DOCUMENTO'=>'Descarga de documento',
        'INTEGRIDAD_DOCUMENTO_FALLIDA'=>'Descarga bloqueada por integridad',
        'VERIFICACION_DOCUMENTO'=>'Verificación de documento',
        'CREACION_USUARIO'=>'Creación de usuario',
        'ACTUALIZACION_USUARIO'=>'Cambio de permisos o estado'
    ][$accion] ?? $accion;
}
function resumenAuditoria(?string $descripcion): string {
    if ($descripcion === null || $descripcion === '') return 'Sin descripción adicional.';
    $datos = json_decode($descripcion, true);
    if (!is_array($datos) || !isset($datos['resultado']) || !is_string($datos['resultado'])) return $descripcion;
    $resultado = ['coincide'=>'Coincide con MySQL', 'diferente'=>'PDF diferente al registro', 'sin_coincidencias'=>'Sin huella coincidente', 'codigo_no_encontrado'=>'Código no encontrado'][$datos['resultado']] ?? $datos['resultado'];
    $modo = ($datos['modo'] ?? '') === 'comparacion_por_codigo' ? 'Comparación por código' : 'Búsqueda por SHA-256';
    return $resultado . ' · ' . $modo;
}
