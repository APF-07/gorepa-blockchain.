# Consulta de auditoría

Implementada el 14 de noviembre de 2026, antes de continuar con la integración Blockchain.

## Uso

Entrar como administrador y abrir Auditoría del sistema en el menú, o http://localhost:8080/gorepa_blockchain/modules/auditoria.php.

Se muestran eventos existentes, usuario, operación, descripción y fecha de MySQL, ordenados del más reciente al más antiguo. Filtros: búsqueda libre, operación, usuario y fechas inicial/final incluidas. Paginación de 20 registros. Los contadores corresponden al conjunto filtrado. Las verificaciones JSON muestran un resumen y un desplegable con la información técnica.

No se puede editar, eliminar ni añadir eventos desde esta pantalla. La vista usa requiereAdministrador y consultas preparadas. Los operadores no tienen enlace de acceso. Este historial de MySQL no es un registro inmutable en Blockchain.

## Archivos

Creados: includes/auditoria.php, modules/auditoria.php y este documento.
Modificados: includes/sidebar.php y assets/css/estilos.css.
Sin modificaciones del esquema, configuración sensible ni datos existentes.

## Comprobaciones ejecutadas

- Sintaxis correcta en los PHP creados/modificados.
- HTTP real: administrador consulta historial; acceso anónimo redirigido al login; POST rechazado con 405.
- Búsqueda preparada y escape HTML comprobados.
- Fechas imposibles, rango invertido e identificador de usuario inválido rechazados con 400.
- Filtros combinados de operación y usuario, detalles JSON y rango de fechas de día completo comprobados.
- Página fuera de rango ajustada al límite real.
- Enlace habilitado en menú administrativo.
- Cantidad de eventos antes y después idéntica: la prueba no creó, editó ni eliminó registros.

No se comprobó con una sesión real de operador en esta entrega; la restricción utiliza el control administrativo existente. Pendiente revisión visual en escritorio y celular. El script temporal de prueba fue retirado.

Esta entrega completa la consulta de auditoría; no da por terminadas las pruebas Blockchain ni la preparación de evidencias del proyecto completo.
