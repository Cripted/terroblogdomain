<?php
/**
 * php/login.php — Terror Digital
 * Endpoint AJAX para login de usuarios públicos.
 * Responde JSON: { success, message, nombre }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$usuario  = trim($_POST['usuario'] ?? '');
$password = $_POST['password'] ?? '';

if (!$usuario || !$password) {
    echo json_encode(['success' => false, 'message' => 'Usuario y contraseña son obligatorios.']);
    exit;
}

// Buscar por username O email
$sql  = "SELECT id, username, nombre_completo, password, rol FROM usuarios WHERE (username = ? OR email = ?) AND activo = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $usuario, $usuario);
$stmt->execute();
$result = $stmt->get_result();
$row    = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado.']);
    exit;
}

if (!password_verify($password, $row['password'])) {
    echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta.']);
    exit;
}

// Iniciar sesión
_sess();
$_SESSION['logged_in']      = true;
$_SESSION['user_id']        = $row['id'];
$_SESSION['username']       = $row['username'];
$_SESSION['nombre_completo']= $row['nombre_completo'];
$_SESSION['rol']            = $row['rol'];

// Actualizar último acceso
$u = $conn->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
$u->bind_param("i", $row['id']);
$u->execute();
$u->close();
$conn->close();

echo json_encode([
    'success' => true,
    'message' => '¡Bienvenido de vuelta, ' . htmlspecialchars($row['nombre_completo']) . '!',
    'nombre'  => $row['nombre_completo'],
    'rol'     => $row['rol'],
]);