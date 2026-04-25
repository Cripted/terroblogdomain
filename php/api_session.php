<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache');

require_once __DIR__ . '/../config/database.php';
_sess();

if (!empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    echo json_encode([
        'loggedIn' => true,
        'nombre'   => $_SESSION['nombre_completo'] ?? $_SESSION['username'] ?? 'Usuario',
        'rol'      => $_SESSION['rol'] ?? 'autor',
    ]);
} else {
    echo json_encode(['loggedIn' => false]);
}