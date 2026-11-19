# Ficha pública mediante QR

El QR principal de Documentos > Ver detalle ahora abre consulta-publica.php?documento=<document_id>. Sustituye al QR que solo mostraba texto SHA-256. La pantalla pública presenta código, tipo, hash, contrato, red, emisor Blockchain, fecha y estado. No requiere login ni MetaMask y no entrega el PDF, asunto, nombre de archivo o datos de cuentas de usuario.

La consulta inicial es neutra. JavaScript solicita una comprobación de lectura por RPC; la tarjeta solo pasa a verificada cuando pruebaBlockchain valida transacción, recibo, evento, hashes y confirmaciones. Un registro sustituido muestra SUSTITUIDO. Una caída RPC muestra NO DISPONIBLE; una falta de coincidencia no muestra verde. Si el RPC no permite la llamada histórica, se consulta el registro actual inmutable y se contrasta igualmente con el evento y las demás evidencias.

El resultado comprueba el registro de referencia; no compara un PDF guardado en el teléfono. No demuestra veracidad jurídica, aprobación de GOREPA o firma digital acreditada.

El enlace usa GOREPA_PUBLIC_BASE_URL si está configurado; en su defecto usa la dirección IPv4 privada del servidor y el puerto. En XAMPP local, teléfono y PC necesitan conectividad de red, normalmente la misma Wi-Fi. No se modificó firewall ni se publicó el sitio en internet. Si cambia la IP del equipo, debe descargarse/escanearse el QR actualizado. La URL pública no se construye a partir de un Host arbitrario.

Los datos de esta ficha son accesibles a quien tenga o derive el identificador documental. El identificador no es un secreto de autenticación. La publicación se limita a los campos técnicos necesarios; no se ofrece búsqueda pública general de documentos.

Botones: Consultar Blockchain, Copiar hash SHA-256, Copiar enlace público y Abrir en Etherscan. La lectura no genera transacciones, no consume gas y no modifica la base de datos. Los PDF ya registrados no se modifican para añadirles un QR, porque eso cambiaría su hash; el QR se descarga como SVG separado.

Archivos nuevos: consulta-publica.php, includes/consulta-publica.php y assets/js/consulta-publica.js. Archivos adaptados: modules/detalle.php, assets/js/documento-qr.js, assets/css/estilos.css y pruebas QR.

Pruebas ejecutadas: sintaxis PHP/JS; cuatro casos QR con decodificador independiente y generación/rechazo de referencias; ocho casos específicos de ficha más los catorce casos existentes del validador; HTTP real anónimo con resultado verificado, fecha/emisor de Sepolia, errores 400/404/405 y ausencia de modificaciones en documentos/transacciones/auditoría; detalle autenticado con enlace correcto. La primera comprobación pública no pudo confirmar por una lectura RPC fallida; la repetición y la comprobación posterior de los casos de tolerancia a fallos pasaron. Lectura con cámara de teléfono real y revisión visual en teléfono: No verificado.
