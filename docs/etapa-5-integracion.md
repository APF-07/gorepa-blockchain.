# Etapa 5 · Integración web con Ethereum Sepolia

Actualización: el usuario desplegó el contrato en Sepolia y su dirección ya fue configurada y comprobada por RPC. Ver [despliegue-sepolia.md](despliegue-sepolia.md). Las referencias a despliegue pendiente más abajo describen el estado de la entrega original; aún falta comprobar el primer registro integral desde la web.

Preparada el 14 de noviembre de 2026. La activación en Sepolia sigue pendiente; no se presenta como un despliegue ya realizado.

## Estado actual

- Contrato compilado con solc 0.8.34, optimización 200 ejecuciones y EVM paris. ABI y selectores/evento generados automáticamente desde esa compilación.
- Pruebas manuales observadas en Remix VM: registro, consulta, coincidencia/diferencia de huellas, autorización/revocación, sustitución con conservación del original y bloqueo de identificador/hash duplicados.
- Módulo web implementado en http://localhost:8080/gorepa_blockchain/modules/blockchain.php. Acceso para administrador y operador; enlace desde cada detalle documental.
- Billetera, registro, lectura de Ethereum, comparación de PDF en navegador y recuperación de transacción implementados con ethers.js 6.15.0 local.
- Ningún despliegue en Sepolia, ninguna firma mediante MetaMask y ningún documento real confirmado en Ethereum en esta entrega.

## Activación sin dinero real

1. Instalar MetaMask desde https://metamask.io/ y crear una billetera dedicada a pruebas. No compartir su frase ni claves privadas.
2. Activar Ethereum Sepolia y obtener ETH de prueba mediante un faucet gratuito. No comprar ETH real. Si no hay fondos gratuitos disponibles, continuar usando Remix VM y las pruebas locales.
3. Desplegar el mismo contrato en Sepolia desde Remix. Configuración reproducible: compilador 0.8.34, optimizador habilitado, 200 runs, EVM paris, Value 0. Revisar red Sepolia antes de firmar.
4. Copiar su dirección pública en blockchain/red.json, campo contractAddress. No usar la dirección de Remix VM. Mantener chainId 11155111 y confirmations al menos 2.
5. Abrir el módulo web, elegir un documento y pulsar Conectar MetaMask. La billetera que desplegó el contrato ya está autorizada como dueña. Otras billeteras se autorizan mediante setOperator por el dueño.
6. Para un registro nuevo, dejar el código anterior vacío. Para una sustitución, introducir el código del registro anterior, que debe existir en el contrato. Registrar en Sepolia y revisar la operación en MetaMask.
7. PHP verifica el recibo y solo entonces guarda el resultado en MySQL. Si se cierra la página, recuperar el hash desde MetaMask o el campo conservado en el navegador y usar Comprobar y guardar confirmación.

La web permanece en XAMPP. No se requiere hosting, dominio ni proveedor RPC de pago. La instalación de paquetes de desarrollo no envía transacciones ni despliega contratos.

## Confirmación del servidor

El cliente no puede declarar por sí mismo una confirmación. PHP obtiene los datos mediante JSON-RPC y comprueba:

- Red Sepolia, código en la dirección configurada y transacción al contrato configurado, con valor cero.
- Hash de transacción, selector registerDocument, identificador documental, hash de PDF y hash de metadatos.
- Recibo, estado exitoso, bloque canónico, evento del contrato y parámetros emitidos.
- Registro consultado mediante eth_call y mínimo configurado de confirmaciones.

Sin recibo o sin confirmaciones suficientes, la transacción queda pendiente. Si el recibo revierte, se guarda como fallida y no se marca el documento como registrado. Las respuestas incongruentes se rechazan.

El guardado usa una transacción PDO, bloqueo del documento y la tabla transacciones existente, junto con auditoría. Repetir la confirmación de la misma transacción no duplica entradas. Los documentos sustituidos conservan sus hashes y pasan a reemplazado. No se alteró el esquema ni se tocaron credenciales.

El RPC público predeterminado es https://ethereum-sepolia-rpc.publicnode.com. Se puede sustituir mediante la variable de entorno GOREPA_SEPOLIA_RPC_URL del servidor, solo HTTPS. No se envía la URL RPC del servidor al JavaScript ni se aceptan URLs desde formularios.

## Alcance y límites

- Dos confirmaciones son un criterio práctico del prototipo, no finalidad absoluta. No hay vigilancia automática continua de reorganizaciones. Si la red deja de confirmar un registro antes confirmado, se rechaza una actualización automática y se requiere revisión.
- Se confía en el proveedor RPC y en la dirección de contrato configurada por el responsable del sistema. No es una auditoría de seguridad externa.
- La comparación de PDF con Ethereum se calcula en el navegador y consulta el contrato mediante MetaMask; no modifica MySQL ni genera un evento de auditoría por sí sola. La etapa 4 sigue ofreciendo comparación/auditoría off-chain.
- La auditoría Blockchain identifica al usuario web que presentó/comprobó la transacción y la billetera emisora. No demuestra que la billetera sea legalmente de esa persona.
- Las firmas de transacciones Ethereum no se presentan como firmas digitales acreditadas de documentos administrativos.
- No hay seguimiento automático ilimitado ni control automático de transacciones reemplazadas/canceladas por MetaMask. Se informa del reemplazo y se permite recuperar el nuevo hash.

## Archivos

Nuevos: includes/blockchain.php; modules/blockchain.php y blockchain-api.php; blockchain/red.json, abi.json y protocolo.json; assets/vendor/ethers.umd.min.js y su licencia; scripts y pruebas privadas de blockchain/dev; este documento y .gitignore para dependencias/fixtures.
Implementado: blockchain/contrato.js.
Modificados: includes/sidebar.php, includes/auditoria.php, modules/detalle.php, modules/dashboard.php, assets/css/estilos.css y la guía de despliegue.
El directorio blockchain/dev está protegido mediante .htaccess. No contiene claves de billeteras reales. Los artefactos públicos solo contienen ABI, selectores y configuración pública.

## Comprobaciones ejecutadas

- npm test en blockchain/dev: 14 pruebas correctas, contrato y conexión/acciones con EVM real en memoria y DOM simulado. Ganache usó su implementación JavaScript de respaldo por incompatibilidad del binario opcional con Node 26; las pruebas terminaron correctamente.
- PHP blockchain/dev/server.test.php: 14 comprobaciones correctas con recibo real producido por la EVM local y respuestas RPC controladas. Se probaron Mainnet, contrato/huellas incorrectas, recibo sin evento, evento falsificado, bloque no canónico, registro inconsistente, pendiente, insuficientes confirmaciones y transacción revertida.
- PHP blockchain/dev/integration.test.php: pruebas HTTP con Apache/MySQL reales del módulo sin configuración, CSRF, acceso y regresiones de los seis módulos anteriores.
- Persistencia probada en tablas temporales aisladas de MySQL, no en los datos reales: pendiente, confirmado, idempotencia, reemplazado y rechazo/rollback de metadatos cambiados. La primera ejecución del montaje de tablas temporales falló por una sintaxis de clonación incompatible con MariaDB; se corrigió y la segunda pasó completa.
- Sintaxis PHP/JavaScript correcta.

No se ejecutó una prueba integral con MetaMask ni una transacción en Sepolia. Tampoco se verificó visualmente en un navegador real. Esas comprobaciones requieren la billetera y el despliegue públicos de pruebas.

Comandos reproducibles desde blockchain/dev: npm test; C:/xampp/php/php.exe server.test.php; C:/xampp/php/php.exe integration.test.php (requiere Apache y MySQL locales). npm run compile regenera ABI, protocolo y la copia local de ethers. Los recibos de pruebas están en fixture.json, dentro del directorio protegido e ignorado.
