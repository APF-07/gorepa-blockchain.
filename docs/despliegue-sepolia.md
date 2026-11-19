# Contrato configurado en Ethereum Sepolia

Dirección pública configurada: 0x33196fA7eCf4309d86896E3Cfd41A60bd5c645a5.
Red: Ethereum Sepolia, chain ID 11155111. Tipo de Blockchain: pública; uso de red de pruebas.
Administrador del contrato según owner(): 0xabede7567dc0f667393df30adf7087889bec6262.

Se consultó el RPC de Sepolia y se confirmó código en la dirección (4620 bytes), presencia de los selectores de registerDocument/getDocument/supersededBy y respuesta válida de owner. Es una comprobación de lectura y compatibilidad básica; no equivale a una auditoría externa ni a una comparación completa del bytecode.

Se modificó únicamente blockchain/red.json para activar la dirección facilitada por el usuario. La herramienta CLI privada blockchain/dev/comprobar-sepolia.php permite repetir la comprobación. No se alteraron tablas, documentos, transacciones o contraseñas ni se enviaron transacciones durante esta configuración.

## Primer registro desde la web

1. Abrir http://localhost:8080/gorepa_blockchain/modules/blockchain.php e iniciar sesión.
2. Elegir el documento ya registrado en MySQL.
3. Pulsar Conectar MetaMask y utilizar la cuenta que desplegó el contrato. Confirmar Sepolia.
4. Para un registro nuevo, dejar el código anterior vacío y pulsar Registrar en Sepolia.
5. Revisar y confirmar en MetaMask. Se consume ETH ficticio de prueba para el gas.
6. Conservar el hash de transacción. El servidor comprobará al menos dos confirmaciones antes de actualizar MySQL. Si se cierra la página o tarda, usar Comprobar y guardar confirmación con ese hash.

Explorador: https://sepolia.etherscan.io/address/0x33196fA7eCf4309d86896E3Cfd41A60bd5c645a5

Pendiente: primera transacción de un documento desde el módulo web, confirmación y persistencia reales en MySQL, y comparación del PDF contra ese registro público. Hasta completarlo no se considera comprobado el recorrido integral en Sepolia.
