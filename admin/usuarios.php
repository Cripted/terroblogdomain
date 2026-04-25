<?php
require_once '../config/auth.php';
$auth->requireRole('admin');
$user = $auth->getCurrentUser();
$db   = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['nuevo_usuario'])) {
        $rol      = in_array($_POST['rol'] ?? '', ['autor','editor','admin']) ? $_POST['rol'] : 'autor';
        $username = sanitize($_POST['username'] ?? '');
        // nombre_completo = username si no se provee
        $r = $auth->register(
            $username,
            sanitize($_POST['email']    ?? ''),
            $_POST['password']          ?? '',
            $username,   // nombre_completo igual al username
            $rol
        );
        setFlashMessage($r['success'] ? 'success' : 'error', $r['message']);
    } elseif (isset($_POST['toggle_id'])) {
        $db->prepare("UPDATE usuarios SET activo = NOT activo WHERE id = ?")->execute([(int)$_POST['toggle_id']]);
        setFlashMessage('success', 'Estado actualizado');
    } elseif (isset($_POST['change_rol'])) {
        $rol = in_array($_POST['nuevo_rol'] ?? '', ['autor','editor','admin']) ? $_POST['nuevo_rol'] : 'autor';
        $db->prepare("UPDATE usuarios SET rol = ? WHERE id = ?")->execute([$rol, (int)$_POST['change_rol']]);
        setFlashMessage('success', 'Rol actualizado');
    }
    redirect(SITE_URL . '/admin/usuarios.php');
}

$users = $db->query("SELECT * FROM usuarios ORDER BY creado_en DESC")->fetchAll();
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios - Admin | Terror Digital</title>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Text:wght@400;600;700&family=Rubik:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
<div class="grain"></div>
<div class="admin-layout">
    <?php include 'partials/sidebar.php'; ?>
    <main class="admin-main">
        <div class="admin-header">
            <h1>👥 Usuarios</h1>
        </div>

        <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>

        <div class="admin-section">
            <h2>Crear usuario</h2>
            <form method="POST" class="admin-form">
                <input type="hidden" name="nuevo_usuario" value="1">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1rem;">
                    <div class="form-group">
                        <label>Usuario *</label>
                        <input type="text" name="username" required placeholder="usuario123">
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" required placeholder="correo@ejemplo.com">
                    </div>
                    <div class="form-group">
                        <label>Contraseña *</label>
                        <input type="password" name="password" required placeholder="Mín. 6 caracteres">
                    </div>
                    <div class="form-group">
                        <label>Rol</label>
                        <select name="rol">
                            <option value="autor">Autor</option>
                            <option value="editor">Editor</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-sm">➕ Crear usuario</button>
            </form>
        </div>

        <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Último acceso</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td>
                    <?php if ($u['id'] != $user['id']): ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="change_rol" value="<?= $u['id'] ?>">
                        <select name="nuevo_rol" onchange="this.form.submit()" style="background:#0a0a0a;border:1px solid #333;color:#ccc;padding:.3rem .5rem;font-size:.82rem;">
                            <?php foreach (['autor','editor','admin'] as $r): ?>
                            <option value="<?= $r ?>" <?= $u['rol'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php else: ?>
                    <span class="badge badge-gold"><?= ucfirst($u['rol']) ?> (tú)</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge <?= $u['activo'] ? 'badge-green' : 'badge-gray' ?>">
                        <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                    </span>
                </td>
                <td><?= $u['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acceso'])) : 'Nunca' ?></td>
                <td>
                    <?php if ($u['id'] != $user['id']): ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="toggle_id" value="<?= $u['id'] ?>">
                        <button type="submit" class="btn-xs">
                            <?= $u['activo'] ? '🚫 Desactivar' : '✅ Activar' ?>
                        </button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </main>
</div>
</body>
</html>