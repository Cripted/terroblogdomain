<?php
/**
 * pages/logout.php — cierra sesión de usuario público y redirige al index.
 */
require_once '../config/auth.php';
$auth->logout();
redirect(SITE_URL . '/index.html');