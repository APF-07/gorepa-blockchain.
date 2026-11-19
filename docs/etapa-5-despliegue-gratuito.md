# Etapa 5 · Ruta de despliegue sin dinero real

Actualización: el usuario desplegó el contrato en Sepolia y su dirección ya fue configurada y comprobada por RPC. Ver [despliegue-sepolia.md](despliegue-sepolia.md). Las referencias a despliegue pendiente más abajo describen el estado de la entrega original; aún falta comprobar el primer registro integral desde la web.

Estado actualizado: contrato compilado, probado manualmente en Remix VM y automáticamente en una EVM local. Integración web implementada y comprobada localmente. Falta instalar MetaMask, desplegar en Sepolia y configurar la dirección del contrato. No se han confirmado documentos reales en Sepolia ni se han modificado las tablas o la configuración sensible.

## Qué significa desplegar

Desplegar es crear una instancia del contrato en una red Ethereum. No publica la web PHP: el sistema web sigue en XAMPP. La instancia recibe una dirección pública de contrato. Cada red tiene su propio estado; desplegar en Remix VM no lo publica en Sepolia.

## Fase A · Remix VM, sin instalar MetaMask

1. Abrir https://remix.ethereum.org/.
2. Crear RegistroDocumentalGOREPA.sol y copiar el archivo blockchain/RegistroDocumentalGOREPA.sol del proyecto.
3. En Solidity Compiler, seleccionar una versión estable compatible con ^0.8.30, compilar y revisar errores y advertencias. Anotar la versión exacta y la configuración.
4. En Deploy & Run Transactions, elegir un entorno Remix VM. No elegir una red pública todavía.
5. Mantener Value en 0 y desplegar. Se usa ETH ficticio de las cuentas de Remix VM.
6. La cuenta que despliega es el administrador del contrato y puede registrar documentos. Otros operadores se autorizan con setOperator.

Remix VM no utiliza dinero real ni requiere una billetera externa. La compilación, el despliegue y las pruebas básicas en Remix VM fueron observados mediante capturas compartidas por el usuario.

## Pruebas en Remix VM

Preparar valores bytes32 distintos: cada valor lleva 0x y 64 dígitos hexadecimales. El cero se expresa como 0x seguido de 64 ceros. Para las pruebas usar huellas ficticias; para la integración se usarán las huellas reales calculadas por PHP.

- Registrar ID A, hash de archivo A y hash de metadatos A con previousId cero. Consultar getDocument y verifyDocument.
- Repetir el ID: debe revertir DuplicateDocument.
- Reutilizar el mismo hash de archivo con otro ID: debe revertir DuplicateFile.
- Cambiar a una cuenta no autorizada e intentar registrar: debe revertir Unauthorized.
- Volver al dueño, autorizar esa cuenta con setOperator y probar el registro con valores nuevos.
- Revocar su autorización y comprobar que vuelve a fallar.
- Registrar ID B con huellas nuevas y previousId A: debe enlazar la sustitución. getDocument(A) debe conservar el registro anterior y supersededBy(A) debe devolver B.
- Intentar otra sustitución directa de A: debe revertir AlreadySuperseded. Para continuar el historial se sustituye B.
- Intentar sustituir un ID inexistente: debe revertir UnknownPreviousDocument.
- Probar transferencia de administración en dos pasos: transferOwnership y acceptOwnership desde la cuenta candidata.

El contrato rechaza reutilizar un mismo PDF aunque se cambie el código documental. Si el proceso administrativo necesita registrar el mismo contenido con varios códigos, esa regla debe revisarse antes de desplegar.

## Fase B · Sepolia, opcional tras las pruebas

1. Instalar MetaMask desde https://metamask.io/ y crear una billetera dedicada a pruebas. Su frase de recuperación se guarda de forma privada; no se introduce en el proyecto ni se comparte por chat.
2. Activar las redes de prueba y seleccionar Ethereum Sepolia (chain ID 11155111).
3. Conseguir ETH de prueba mediante un faucet gratuito. Algunos tienen límites o requisitos de elegibilidad; no comprar ETH real ni pagar para conseguir fondos de prueba. Si no se consigue un faucet sin coste, continuar con Remix VM o una EVM local.
4. En Remix, conectar la billetera del navegador y comprobar explícitamente Sepolia antes de desplegar. Value permanece en 0. MetaMask mostrará una comisión de red que consume Sepolia ETH de prueba.
5. Confirmar personalmente la transacción y guardar dirección del contrato, hash de transacción, ABI, versión del compilador y configuración de compilación.
6. El contrato podrá consultarse mediante https://sepolia.etherscan.io/.

No se necesita hosting, dominio ni servicio RPC de pago para el prototipo. Los servicios públicos gratuitos tienen límites de disponibilidad.

## Integración web implementada

Ver docs/etapa-5-integracion.md para configuración, pruebas y límites. El nuevo módulo está en http://localhost:8080/gorepa_blockchain/modules/blockchain.php.

La dirección de Remix VM no sirve como dirección de Sepolia. El módulo permanece sin envío hasta configurar un despliegue real en Sepolia. No hay despliegue automático ni gasto de fondos reales.

## Referencias oficiales

- Redes y fondos de prueba: https://ethereum.org/developers/docs/networks/
- Remix VM y despliegue: https://remix-ide.readthedocs.io/en/latest/create_deploy.html
- Faucet de prueba: https://support.metamask.io/develop/blockchain-networks/testnets/using-the-testnet-faucet/
