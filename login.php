<?php
require_once __DIR__ . '/includes/auth.php';
ob_start();
require_once __DIR__ . '/config/database.php';
ob_end_clean();

if (!empty($_SESSION['usuario_id'])) {
    requiereLogin();
    header('Location: /gorepa_blockchain/modules/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrfValido($_POST['csrf_token'] ?? null)) {
    $error = 'La sesión del formulario venció. Intenta nuevamente.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = is_string($_POST['correo'] ?? null) ? trim($_POST['correo']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    $stmt = $conexion->prepare(
        'SELECT * FROM usuarios
         WHERE correo = ? AND activo = 1
         LIMIT 1'
    );

    $stmt->execute([$correo]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify(
        $password,
        $usuario['password_hash']
    )) {
        session_regenerate_id(true);

        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['nombre'] = $usuario['nombre'];
        $_SESSION['rol'] = $usuario['rol'];

        header('Location: /gorepa_blockchain/modules/dashboard.php');
        exit;
    }

    $error = 'Correo o contraseña incorrectos.';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión - GOREPA</title>
    <link rel="stylesheet" href="assets/css/estilos.css?v=<?= filemtime(__DIR__ . '/assets/css/estilos.css') ?>">
</head>
<body class="login-body">

    <main class="login-card">
        <div class="logo">G</div>

        <h1>GOREPA Blockchain</h1>
        <p>Sistema de Registro y Verificación Documental</p>

        <h2>Iniciar sesión</h2>

        <?php if ($error): ?>
            <p class="error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
            <label for="correo">Correo electrónico</label>
            <input id="correo" type="email" name="correo" autocomplete="username" required>

            <label for="password">Contraseña</label>
            <input id="password" type="password"
                   name="password" autocomplete="current-password" required>

            <button type="submit">Ingresar al sistema</button>
        </form>

        <small>Prototipo académico · UNDAC 2026. No es un sistema oficial de GOREPA.</small>
    </main>

</body>
</html>
