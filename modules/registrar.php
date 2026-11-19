<?php
require_once __DIR__ . '/../includes/documentos.php';
requiereGestionDocumental();
require_once __DIR__ . '/../config/database.php';

requiereLogin();

if (!in_array($_SESSION['rol'] ?? '', ['administrador', 'operador'], true)) {
    http_response_code(403);
    exit('No tienes permiso para registrar documentos.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensaje = '';
$error = '';
$codigo = '';
$tipo = '';
$asunto = '';
$documentoCreado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = (is_string($_POST['codigo'] ?? null) ? trim($_POST['codigo']) : '');
    $tipo = (is_string($_POST['tipo'] ?? null) ? trim($_POST['tipo']) : '');
    $asunto = (is_string($_POST['asunto'] ?? null) ? trim($_POST['asunto']) : '');

    $archivoGuardado = null;
    $bloqueoAdquirido = false;
    $nombreBloqueo = 'gorepa_registro_documental';

    try {
        // Validar token de seguridad
        if (!csrfValido($_POST['csrf_token'] ?? null)) {
            throw new Exception('Solicitud no válida.');
        }

        // Validar campos
        if ($codigo === '' || $tipo === '' || $asunto === '') {
            throw new Exception('Completa todos los campos.');
        }

        if (strlen($codigo) > 60 || strlen($asunto) > 255) {
            throw new Exception('Los campos superan la longitud permitida.');
        }

        $tiposPermitidos = tiposDocumentales();

        if (!in_array($tipo, $tiposPermitidos, true)) {
            throw new Exception('Tipo de documento no válido.');
        }

        // Validar subida
        if (
            !isset($_FILES['documento']) ||
            $_FILES['documento']['error'] !== UPLOAD_ERR_OK
        ) {
            throw new Exception('No se pudo recibir el archivo.');
        }

        $archivo = $_FILES['documento'];
        $temporal = $archivo['tmp_name'];

        if ($archivo['size'] <= 0 || $archivo['size'] > 5 * 1024 * 1024) {
            throw new Exception('El PDF debe tener como máximo 5 MB.');
        }

        if (!is_uploaded_file($temporal)) {
            throw new Exception('Archivo no válido.');
        }

        $extension = strtolower(
            pathinfo($archivo['name'], PATHINFO_EXTENSION)
        );

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($temporal);

        $fp = fopen($temporal, 'rb');
        $cabecera = fread($fp, 5);
        fclose($fp);

        if (
            $extension !== 'pdf' ||
            $mime !== 'application/pdf' ||
            $cabecera !== '%PDF-'
        ) {
            throw new Exception('Solo se permiten archivos PDF válidos.');
        }

        // Generar identificadores y hashes
        $fileHash = hash_file('sha256', $temporal);

        $documentId = hash('sha256', 'GOREPA:' . $codigo);

        // Metadatos ordenados en formato JSON UTF-8
        $metadatos = json_encode([
            'codigo' => $codigo,
            'tipo' => $tipo,
            'asunto' => $asunto
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($metadatos === false) {
            throw new Exception('Error al generar los metadatos.');
        }

        $metadataHash = hash('sha256', $metadatos);

        // Serializar registros concurrentes sin modificar el esquema existente.
        $bloqueo = $conexion->prepare('SELECT GET_LOCK(?, 5)');
        $bloqueo->execute([$nombreBloqueo]);
        $bloqueoAdquirido = (int) $bloqueo->fetchColumn() === 1;
        if (!$bloqueoAdquirido) {
            throw new Exception('Hay otro registro en curso. Intenta nuevamente en unos segundos.');
        }

        // Comprobar código duplicado
        $consulta = $conexion->prepare(
            'SELECT id FROM documentos WHERE codigo = ?'
        );

        $consulta->execute([$codigo]);

        if ($consulta->fetch()) {
            throw new Exception('Este código de documento ya existe.');
        }

        // Guardar archivo con nombre aleatorio
        $nombreInterno = bin2hex(random_bytes(16)) . '.pdf';
        $directorio = __DIR__ . '/../storage/documentos/';

        if (!is_dir($directorio)) {
            throw new Exception('No existe la carpeta de almacenamiento.');
        }

        $archivoGuardado = $directorio . $nombreInterno;

        if (!move_uploaded_file($temporal, $archivoGuardado)) {
            throw new Exception('No se pudo guardar el documento.');
        }

        // Guardar información en MySQL
        $conexion->beginTransaction();

        $sql = 'INSERT INTO documentos
                (codigo, tipo, asunto, archivo_nombre,
                 archivo_ruta, file_hash, metadata_hash,
                 document_id, estado, usuario_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

        $stmt = $conexion->prepare($sql);

        $stmt->execute([
            $codigo,
            $tipo,
            $asunto,
            basename($archivo['name']),
            'storage/documentos/' . $nombreInterno,
            $fileHash,
            $metadataHash,
            $documentId,
            'pendiente',
            $_SESSION['usuario_id']
        ]);

        $documentoIdBD = $conexion->lastInsertId();

        // Registrar auditoría
        $auditoria = $conexion->prepare(
            'INSERT INTO auditoria
             (usuario_id, accion, descripcion)
             VALUES (?, ?, ?)'
        );

        $auditoria->execute([
            $_SESSION['usuario_id'],
            'REGISTRO_DOCUMENTO',
            'Documento ID ' . $documentoIdBD
        ]);

        $conexion->commit();
        $documentoCreado = (int) $documentoIdBD;
        $codigo = $tipo = $asunto = '';

        $mensaje = 'Documento registrado correctamente en MySQL. Estado: pendiente de Blockchain.';

        // Limpiar token después de guardar
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    } catch (Throwable $e) {

        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        // Evitar archivos huérfanos si falló la operación
        if ($archivoGuardado !== null && is_file($archivoGuardado)) {
            unlink($archivoGuardado);
        }

        error_log($e->getMessage());

        if ($e instanceof PDOException) {
            $error = 'No se pudo registrar el documento en la base de datos.';
        } else {
            $error = $e->getMessage();
        }
    } finally {
        if ($bloqueoAdquirido) {
            $liberar = $conexion->prepare('SELECT RELEASE_LOCK(?)');
            $liberar->execute([$nombreBloqueo]);
        }
    }
}
?>

<?php $tituloPagina = 'Registrar documento'; require __DIR__ . '/../includes/header.php'; ?>
<section class="page-heading"><div><p class="eyebrow">GESTIÓN DOCUMENTAL</p><h1>Registrar documento</h1><p>Registra un PDF ficticio y genera su huella SHA-256.</p></div><a class="action-btn action-secondary" href="documentos.php"><?= iconoUi('lista') ?><span>Ver documentos</span><?= iconoUi('flecha') ?></a></section>
<div class="dashboard-card form-card">
        <?php if ($mensaje): ?>
            <p class="alerta-exito" role="status">
                <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <?php if ($error): ?>
            <p class="error" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <?php if ($documentoCreado !== null): ?><p><a class="action-btn action-secondary" href="detalle.php?id=<?= $documentoCreado ?>"><?= iconoUi('ojo') ?><span>Ver documento registrado</span><?= iconoUi('flecha') ?></a></p><?php endif; ?>
        <ol class="registration-steps"><li>Completa los metadatos</li><li>Adjunta un PDF ficticio de hasta 5 MB</li><li>Registra su huella SHA-256</li></ol>
        <form method="POST" enctype="multipart/form-data">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

            <label for="codigo">Código del documento</label>
            <input type="text"
                   id="codigo"
                   name="codigo"
                   maxlength="60"
                   placeholder="RES-001-2026-GOREPA"
                   value="<?= escapar($codigo) ?>"
                   required>

            <label for="tipo">Tipo de documento</label>
            <select id="tipo" name="tipo" required>
                <option value="">Seleccione una opción</option>
                <option <?= $tipo === 'Resolución administrativa' ? 'selected' : '' ?>>Resolución administrativa</option>
                <option <?= $tipo === 'Resolución ejecutiva regional' ? 'selected' : '' ?>>Resolución ejecutiva regional</option>
                <option <?= $tipo === 'Informe administrativo' ? 'selected' : '' ?>>Informe administrativo</option>
                <option <?= $tipo === 'Otro documento' ? 'selected' : '' ?>>Otro documento</option>
            </select>

            <label for="asunto">Asunto</label>
            <textarea id="asunto"
                      name="asunto"
                      maxlength="255"
                      rows="3"
                      required><?= escapar($asunto) ?></textarea>

            <label for="documento">Documento PDF</label>
            <input type="file"
                   id="documento"
                   name="documento"
                   accept=".pdf,application/pdf"
                   required>

            <p class="texto-ayuda">
                Tamaño máximo: 5 MB. Utiliza documentos ficticios. Una vez registrado, el PDF no se puede reemplazar desde el sistema.
            </p>

            <button class="action-btn action-primary" type="submit"><?= iconoUi('mas') ?><span>Registrar documento</span></button>

        </form>
    </div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
