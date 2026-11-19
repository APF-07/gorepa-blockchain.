<?php
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['usuario_id'])) {
    header('Location: /gorepa_blockchain/modules/dashboard.php');
} else {
    header('Location: /gorepa_blockchain/login.php');
}

exit;
