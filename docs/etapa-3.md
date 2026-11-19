# Etapa 3 · Administración de usuarios

Implementada y comprobada el 14 de noviembre de 2026, sin cambios de estructura de la base de datos.

## Uso

1. Iniciar sesión con una cuenta administradora en http://localhost:8080/gorepa_blockchain/.
2. Abrir Administrar usuarios en el menú o http://localhost:8080/gorepa_blockchain/modules/usuarios.php.
3. Buscar por nombre o correo y filtrar cuentas activas/inactivas. La tabla muestra 15 usuarios por página.
4. En Nuevo operador, escribir nombre, correo único, contraseña y confirmación. Las cuentas se crean activas con rol operador.
5. En otra cuenta, seleccionar rol y estado, y pulsar Guardar. Las operaciones usan POST, CSRF, consultas preparadas y auditoría dentro de la misma transacción.
6. Probar el acceso del operador en una ventana privada. El operador gestiona documentos, pero no puede administrar usuarios.

## Seguridad y alcance

- Contraseñas de 12 a 72 bytes mediante password_hash; no se guardan ni registran contraseñas en texto plano. Las contraseñas existentes siguen siendo válidas.
- No se permite modificar el rol o estado de la cuenta propia ni retirar al último administrador activo.
- Cada solicitud autenticada vuelve a consultar usuario, estado y rol. La desactivación invalida la sesión en la siguiente solicitud; los cambios de rol se reflejan sin volver a iniciar sesión. Una solicitud ya en curso no se cancela retroactivamente.
- Las mutaciones revalidan los permisos del administrador dentro de una transacción con bloqueo; la aplicación serializa los cambios administrativos mediante GET_LOCK.
- No hay eliminación de usuarios, edición de contraseñas, envío de correos ni recuperación de acceso en esta etapa.
- El login ahora incluye protección CSRF. Si había una pestaña del login abierta antes del cambio, actualizarla antes de ingresar.
- Auditoría: CREACION_USUARIO y ACTUALIZACION_USUARIO, con identificador y cambios de rol/estado, sin contraseñas.

## Archivos

Nuevos: includes/usuarios.php, implementación de modules/usuarios.php (antes vacío) y este documento.
Modificados: includes/auth.php, includes/sidebar.php, login.php, logout.php y assets/css/estilos.css.
No se modificó config/database.php ni se alteraron tablas.

## Pruebas ejecutadas

Sintaxis correcta en todos los PHP. Pruebas HTTP con Apache y MySQL locales:

- Acceso anónimo redirigido al login; administrador autorizado; operador rechazado tanto en GET como POST.
- Token CSRF inválido rechazado (403).
- Protección del administrador propio/último activo.
- Confirmación de contraseña y correo duplicado rechazados.
- Creación real de operador, hash de contraseña verificado y login real correcto.
- Búsqueda y salida HTML escapada.
- Promoción/democión con actualización de permisos en una sesión ya abierta.
- Desactivación con invalidación de sesión abierta y rechazo del login.
- Reactivación con login correcto.
- Auditoría de creación y modificaciones comprobada.
- Dashboard, documentos y registro siguen respondiendo correctamente.

La cuenta de prueba «Operador QA <etapa3>», ID 2, se conservó como operador inactivo. Sus credenciales aleatorias no se publicaron. Las operaciones de prueba permanecen en auditoría. Ninguna cuenta previa fue modificada ni eliminada. El script temporal fue retirado.

Pendiente: revisión visual manual en escritorio/celular y paginación con más de 15 usuarios. No se realizaron pruebas de concurrencia. La etapa 4 (comparación de PDF cargado contra MySQL) y Blockchain siguen pendientes.
