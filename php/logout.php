<?php
/**
 * php/logout.php — Terror Digital
 * Cierra la sesión y redirige al index.
 */
require_once __DIR__ . '/../config/database.php';
_sess();
$_SESSION = [];
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}
session_destroy();
header('Location: ' . SITE_URL . '/index.html');
exit;