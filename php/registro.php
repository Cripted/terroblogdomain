<?php
/**
 * php/registro.php — Terror Digital
 * Endpoint AJAX para registro de nuevos usuarios.
 * Responde JSON: { success, message }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$usuario  = trim($_POST['usuario']  ?? '');
$email    = trim($_POST['email']    ?? '');
$password = $_POST['password']      ?? '';
$confirmar= $_POST['confirmar']     ?? '';

// Validaciones
if (!$usuario || !$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'El email no es válido.']);
    exit;
}
if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres.']);
    exit;
}
if ($password !== $confirmar) {
    echo json_encode(['success' => false, 'message' => 'Las contraseñas no coinciden.']);
    exit;
}
if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $usuario)) {
    echo json_encode(['success' => false, 'message' => 'El usuario solo puede contener letras, números y _ (mínimo 3 caracteres).']);
    exit;
}

// Verificar duplicado
$check = $conn->prepare("SELECT id FROM usuarios WHERE username = ? OR email = ?");
$check->bind_param("ss", $usuario, $email);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    $check->close();
    echo json_encode(['success' => false, 'message' => 'El usuario o email ya está registrado.']);
    exit;
}
$check->close();

// Insertar — nombre_completo = username
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO usuarios (username, email, password, nombre_completo, rol) VALUES (?, ?, ?, ?, 'autor')");
$stmt->bind_param("ssss", $usuario, $email, $hash, $usuario);

if ($stmt->execute()) {
    $newId = $conn->insert_id;
    $stmt->close();

    // Auto-login
    _sess();
    $_SESSION['logged_in']       = true;
    $_SESSION['user_id']         = $newId;
    $_SESSION['username']        = $usuario;
    $_SESSION['nombre_completo'] = $usuario;
    $_SESSION['rol']             = 'autor';

    echo json_encode([
        'success' => true,
        'message' => "¡Bienvenido, $usuario! Tu cuenta ha sido creada.",
        'nombre'  => $usuario,
    ]);
} else {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Error al registrar. Intenta de nuevo.']);
}

$conn->close();