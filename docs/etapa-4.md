# Etapa 4 · Verificación documental off-chain

Implementada y comprobada el 14 de noviembre de 2026. Sin cambios en el esquema de MySQL ni implementación de Blockchain.

## Uso

1. Entrar como administrador u operador a http://localhost:8080/gorepa_blockchain/modules/verificar.php o elegir Verificar documento en el menú.
2. Seleccionar un PDF ficticio de hasta 5 MB.
3. Escribir el código documental esperado para comparar contra ese registro. También se puede abrir el detalle y pulsar Verificar otro PDF con este registro: el código se completa automáticamente.
4. Si se deja el código vacío, se busca el SHA-256 entre los registros de MySQL. Se muestran hasta 20 coincidencias recientes e indicación del total si hay más.
5. Pulsar Verificar integridad y consultar el resultado, las huellas y los enlaces al detalle documental.

## Resultados

- Coincide: el SHA-256 cargado coincide con el guardado en MySQL.
- Diferente: el código existe, pero la huella no coincide. Puede tratarse de un archivo modificado o de otro PDF seleccionado por error.
- Sin coincidencias: búsqueda por hash sin registro coincidente; no permite concluir por sí sola que el PDF fue alterado.
- Código no encontrado: el código de referencia no existe.

La comparación depende de la base de datos de la aplicación. No consulta Ethereum, no valida firmas digitales ni acredita autoría, aprobación oficial o autenticidad jurídica.

## Seguridad y auditoría

Se exige sesión, rol administrador/operador y token CSRF. Se valida carga HTTP, extensión PDF, MIME, cabecera y tamaño real antes de calcular SHA-256. Las consultas usan parámetros preparados y las salidas HTML se escapan.

No se mueve el PDF cargado al almacenamiento permanente ni se reemplazan archivos. PHP elimina su archivo temporal al finalizar la solicitud. La aplicación no convierte ni muestra el contenido del PDF.

Cada comparación completada inserta VERIFICACION_DOCUMENTO en auditoría con usuario, resultado, modo, huella, identificadores de referencia y fuente MySQL. Si no se puede registrar la auditoría, no se presenta una comparación exitosa. Las cargas inválidas no se presentan como verificaciones completadas ni se auditan con esta acción.

## Archivos

Nuevos: includes/verificacion.php y este documento.
Implementado: modules/verificar.php, previamente vacío.
Modificados: includes/sidebar.php, modules/detalle.php y assets/css/estilos.css.

## Comprobaciones ejecutadas

Sintaxis PHP correcta. Pruebas HTTP reales con Apache y MySQL:

- Acceso anónimo redirigido al login y CSRF inválido rechazado (403).
- PDF original: coincidencia con código y visualización de SHA-256.
- Copia temporal con bytes añadidos: diferencia detectada y ambas huellas visibles.
- Original con otro nombre: coincidencia por hash.
- PDF modificado sin código: sin coincidencias.
- Código inexistente con texto de inyección SQL: no encontrado.
- Texto disfrazado de PDF, extensión incorrecta, archivo vacío, archivo mayor de 5 MB y ausencia de archivo: rechazados.
- Cinco comparaciones válidas registradas en auditoría, cubriendo todos los resultados.
- Hash del original, número de documentos y contenido de la carpeta de almacenamiento sin cambios.
- Enlace desde detalle correcto; dashboard, listado, registro y administración de usuarios responden correctamente.

Las copias y el script temporal de prueba fueron retirados. Se conservan las cinco entradas de auditoría, sin crear documentos ni cambiar usuarios.

Pendiente de comprobación manual: apariencia en escritorio y celular. No se simuló caída de MySQL ni fallo de inserción en auditoría. No se probaron más de 20 coincidencias. Las etapas de Blockchain y consulta general de auditoría siguen pendientes.
