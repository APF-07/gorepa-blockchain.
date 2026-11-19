# Etapa 2 · Gestión documental

Implementada el 14 de noviembre de 2026. Prototipo académico, sin cambios de esquema ni integración Blockchain.

## Uso

1. Iniciar Apache (puerto 8080) y MySQL en XAMPP.
2. Iniciar sesión en http://localhost:8080/gorepa_blockchain/.
3. Abrir Documentos: buscar por código o asunto, combinar tipo y estado, o limpiar filtros. Se muestran 15 registros por página.
4. Abrir Ver detalle para consultar metadatos, SHA-256 completo e identificadores técnicos.
5. Usar Descargar PDF: exige rol administrador u operador, sesión y token CSRF. Comprueba SHA-256 frente al registro MySQL y registra la descarga en auditoría. Un PDF con huella diferente se bloquea; esto no constituye verificación Blockchain.
6. Registrar un PDF ficticio de hasta 5 MB con un código nuevo. Los campos se conservan si ocurre un error. No existe una operación de reemplazo o edición del PDF.
7. Cerrar sesión con el botón del menú. La operación utiliza POST con CSRF e invalida la sesión y la cookie.

## Archivos

Nuevos: includes/documentos.php, modules/detalle.php, modules/descargar.php y este documento.
Modificados: includes/auth.php, includes/sidebar.php, modules/dashboard.php, modules/documentos.php, modules/registrar.php, logout.php y assets/css/estilos.css.

La validación existente de PDF, el almacenamiento protegido y la auditoría de registro se conservan. Un bloqueo MySQL GET_LOCK serializa el registro desde esta aplicación y evita la carrera entre la comprobación de duplicados y la inserción sin modificar el esquema. Importaciones o escrituras externas a la aplicación no participan en este bloqueo.

## Comprobaciones ejecutadas

- Sintaxis PHP de todos los archivos: correcta.
- HTTP real contra Apache y MySQL: dashboard, listado, formulario y detalle respondieron correctamente, sin advertencias PHP en las respuestas.
- Listado y descarga sin sesión redirigen al login.
- Búsqueda con texto de inyección SQL no produce coincidencias ni altera la consulta.
- Filtros combinados por código, tipo y estado devuelven el documento existente.
- Identificador inválido: HTTP 400; documento inexistente: HTTP 404.
- Descarga mediante GET: HTTP 405; token CSRF inválido: HTTP 403.
- Detalle muestra el SHA-256 guardado.
- Rutas fuera de storage/documentos se rechazan. Acceso HTTP directo al PDF: HTTP 403.
- Subida del PDF existente con el mismo código: rechazada. El número de documentos permanece igual.
- Descarga autenticada: HTTP 200; el SHA-256 de los bytes recibidos coincide con MySQL. Se confirmó su registro en auditoría.
- Cierre por GET: HTTP 405; CSRF inválido: HTTP 403; cierre válido: redirección al login y sesión invalidada.

No se crearon documentos nuevos ni se modificaron documentos existentes durante estas pruebas. La prueba de descarga generó un registro real de auditoría. El script temporal de comprobación fue retirado.

## Pendiente de comprobación manual

- Apariencia en navegador de escritorio y celular.
- Registro exitoso de un PDF nuevo con código nuevo.
- Navegación entre varias páginas cuando existan más de 15 coincidencias.
- Rama de bloqueo de archivo alterado y concurrencia: no se alteraron archivos originales ni se simularon registros paralelos.

Las etapas de administración de usuarios, verificación por carga de archivos y Blockchain no se implementan en esta entrega.
