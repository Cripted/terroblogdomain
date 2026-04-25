<?php
/**
 * config/auth.php — Terror Digital
 * Sistema de autenticación. Corregido: $this->db se inicializa correctamente.
 */

require_once __DIR__ . '/database.php';

class Auth {
    private $db;

    public function __construct() {
        $this->db = getDB();
        $this->initSession();
    }

    private function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_set_cookie_params(SESSION_LIFETIME);
            session_start();
        }
    }

    // ── Registro ──────────────────────────────────────────────────────────────
    public function register($username, $email, $password, $nombreCompleto, $rol = 'autor') {
        try {
            if (empty($username) || empty($email) || empty($password) || empty($nombreCompleto)) {
                return ['success' => false, 'message' => 'Todos los campos son obligatorios'];
            }
            if (strlen($password) < 6) {
                return ['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres'];
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'El email no es válido'];
            }

            $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'El usuario o email ya existe'];
            }

            $rolesValidos = ['autor', 'editor', 'admin'];
            $rol = in_array($rol, $rolesValidos) ? $rol : 'autor';

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("
                INSERT INTO usuarios (username, email, password, nombre_completo, rol)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $email, $hashedPassword, $nombreCompleto, $rol]);
            return ['success' => true, 'message' => 'Usuario registrado exitosamente'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al registrar: ' . $e->getMessage()];
        }
    }

    // ── Login ─────────────────────────────────────────────────────────────────
    public function login($username, $password, $remember = false) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, username, email, password, nombre_completo, rol, avatar
                FROM usuarios
                WHERE (username = ? OR email = ?) AND activo = TRUE
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => false, 'message' => 'Usuario no encontrado o inactivo'];
            }
            if (!password_verify($password, $user['password'])) {
                return ['success' => false, 'message' => 'Contraseña incorrecta'];
            }

            // Crear sesión
            $_SESSION['user_id']         = $user['id'];
            $_SESSION['username']        = $user['username'];
            $_SESSION['email']           = $user['email']  ?? '';
            $_SESSION['nombre_completo'] = $user['nombre_completo'] ?? $user['username'];
            $_SESSION['rol']             = $user['rol']    ?? 'autor';
            $_SESSION['avatar']          = $user['avatar'] ?? null;
            $_SESSION['logged_in']       = true;

            // Último acceso
            $this->db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?")
                      ->execute([$user['id']]);

            // Cookie "recuérdame"
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + 86400 * 30, '/', '', false, true);
            }

            return ['success' => true, 'message' => 'Sesión iniciada correctamente', 'user' => $user];

        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al iniciar sesión: ' . $e->getMessage()];
        }
    }

    // ── Logout ────────────────────────────────────────────────────────────────
    public function logout() {
        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) setcookie(session_name(), '', time() - 3600, '/');
        if (isset($_COOKIE['remember_token'])) setcookie('remember_token', '', time() - 3600, '/');
        session_destroy();
        return ['success' => true, 'message' => 'Sesión cerrada'];
    }

    // ── Estado ────────────────────────────────────────────────────────────────
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public function getCurrentUser() {
        if (!$this->isLoggedIn()) return null;
        return [
            'id'             => $_SESSION['user_id']         ?? 0,
            'username'       => $_SESSION['username']        ?? '',
            'email'          => $_SESSION['email']           ?? '',
            'nombre_completo'=> $_SESSION['nombre_completo'] ?? '',
            'rol'            => $_SESSION['rol']             ?? 'autor',
            'avatar'         => $_SESSION['avatar']          ?? null,
        ];
    }

    // ── Roles ─────────────────────────────────────────────────────────────────
    public function hasRole($role) {
        if (!$this->isLoggedIn()) return false;
        $roles    = ['autor' => 1, 'editor' => 2, 'admin' => 3];
        $userRole = $_SESSION['rol'] ?? 'autor';
        return isset($roles[$userRole], $roles[$role]) && $roles[$userRole] >= $roles[$role];
    }

    // ── Middleware ────────────────────────────────────────────────────────────
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            setFlashMessage('error', 'Debes iniciar sesión para acceder a esta página');
            redirect(SITE_URL . '/admin/login.php');
        }
    }

    public function requireRole($role) {
        $this->requireLogin();
        if (!$this->hasRole($role)) {
            setFlashMessage('error', 'No tienes permisos para acceder a esta página');
            redirect(SITE_URL . '/admin/index.php');
        }
    }

    // ── Cambiar contraseña ────────────────────────────────────────────────────
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            $stmt = $this->db->prepare("SELECT password FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($currentPassword, $user['password'])) {
                return ['success' => false, 'message' => 'La contraseña actual es incorrecta'];
            }

            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $this->db->prepare("UPDATE usuarios SET password = ? WHERE id = ?")
                     ->execute([$hashedPassword, $userId]);

            return ['success' => true, 'message' => 'Contraseña actualizada correctamente'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al cambiar contraseña: ' . $e->getMessage()];
        }
    }

    // ── Actualizar perfil ─────────────────────────────────────────────────────
    public function updateProfile($userId, $data) {
        try {
            $fields = []; $values = [];

            foreach (['nombre_completo', 'email', 'avatar'] as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($fields)) return ['success' => false, 'message' => 'No hay datos para actualizar'];

            $values[] = $userId;
            $this->db->prepare("UPDATE usuarios SET " . implode(', ', $fields) . " WHERE id = ?")
                     ->execute($values);

            foreach (['nombre_completo', 'email', 'avatar'] as $field) {
                if (isset($data[$field])) $_SESSION[$field] = $data[$field];
            }

            return ['success' => true, 'message' => 'Perfil actualizado correctamente'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al actualizar perfil: ' . $e->getMessage()];
        }
    }
}

// Instancia global
$auth = new Auth();