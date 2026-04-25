<?php
require_once '../config/auth.php';
$auth->requireLogin();
$user = $auth->getCurrentUser();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (isset($_POST['update_profile'])) {
        $data=['nombre_completo'=>sanitize($_POST['nombre_completo']??''),'email'=>sanitize($_POST['email']??'')];
        if (!empty($_FILES['avatar']['name'])) {
            $up=uploadImage($_FILES['avatar'],'avatar');
            if ($up['success']) $data['avatar']=$up['filename'];
            else { setFlashMessage('error',$up['message']); redirect(SITE_URL.'/admin/perfil.php'); }
        }
        $r=$auth->updateProfile($user['id'],$data);
        setFlashMessage($r['success']?'success':'error',$r['message']);
    } elseif (isset($_POST['change_password'])) {
        $cur=$_POST['current_password']??''; $new=$_POST['new_password']??''; $con=$_POST['confirm_password']??'';
        if ($new!==$con) setFlashMessage('error','Las contraseñas no coinciden');
        elseif (strlen($new)<6) setFlashMessage('error','Mínimo 6 caracteres');
        else { $r=$auth->changePassword($user['id'],$cur,$new); setFlashMessage($r['success']?'success':'error',$r['message']); }
    }
    redirect(SITE_URL.'/admin/perfil.php');
}
$s=$conn->prepare("SELECT * FROM usuarios WHERE id=?"); $s->bind_param("i",$user['id']); $s->execute();
$ud=$s->get_result()->fetch_assoc(); $s->close();
$na=$conn->prepare("SELECT COUNT(*) FROM articulos WHERE autor_id=?"); $na->bind_param("i",$user['id']); $na->execute();
$myArts=$na->get_result()->fetch_row()[0]; $na->close();
$flash=getFlashMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Admin | Terror Digital</title>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Text:wght@400;600;700&family=Rubik:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css"><link rel="stylesheet" href="../css/admin.css">
</head>
<body>
<div class="grain"></div>
<div class="admin-layout">
    <?php include 'partials/sidebar.php'; ?>
    <main class="admin-main">
        <div class="admin-header"><h1>👤 Mi Perfil</h1></div>
        <?php if ($flash): ?><div class="alert alert-<?= $flash['type']==='error'?'error':'success' ?>"><?= htmlspecialchars($flash['message']) ?></div><?php endif; ?>
        <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:2rem;">
            <div class="stat-box"><div class="stat-icon">📰</div><div class="stat-info"><span class="stat-num"><?= $myArts ?></span><span class="stat-lbl">Mis artículos</span></div></div>
            <div class="stat-box"><div class="stat-icon">🎭</div><div class="stat-info"><span class="stat-num"><?= strtoupper($ud['rol']) ?></span><span class="stat-lbl">Rol</span></div></div>
            <div class="stat-box"><div class="stat-icon">📅</div><div class="stat-info"><span class="stat-num"><?= date('d/m/Y',strtotime($ud['creado_en'])) ?></span><span class="stat-lbl">Miembro desde</span></div></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;">
            <div class="admin-section">
                <h2>Datos del perfil</h2>
                <?php if ($ud['avatar']): ?>
                <div style="text-align:center;margin-bottom:1.5rem;">
                    <img src="../uploads/<?= htmlspecialchars($ud['avatar']) ?>" style="width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid var(--blood-red);" alt="Avatar">
                </div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data" class="admin-form">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="form-group"><label>Nombre completo</label><input type="text" name="nombre_completo" value="<?= htmlspecialchars($ud['nombre_completo']) ?>" required></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($ud['email']) ?>" required></div>
                    <div class="form-group"><label>Usuario</label><input type="text" value="<?= htmlspecialchars($ud['username']) ?>" disabled style="opacity:.5;"></div>
                    <div class="form-group"><label>Avatar (JPG, PNG — máx 5MB)</label><input type="file" name="avatar" accept=".jpg,.jpeg,.png,.gif,.webp"></div>
                    <button type="submit" class="btn-primary">💾 Guardar</button>
                </form>
            </div>
            <div class="admin-section">
                <h2>Cambiar contraseña</h2>
                <form method="POST" class="admin-form">
                    <input type="hidden" name="change_password" value="1">
                    <div class="form-group"><label>Contraseña actual</label><input type="password" name="current_password" required></div>
                    <div class="form-group"><label>Nueva contraseña</label><input type="password" name="new_password" required minlength="6"></div>
                    <div class="form-group"><label>Confirmar nueva contraseña</label><input type="password" name="confirm_password" required minlength="6"></div>
                    <button type="submit" class="btn-primary">🔑 Cambiar contraseña</button>
                </form>
                <p style="margin-top:1.5rem;color:#555;font-size:.85rem;">Último acceso: <?= $ud['ultimo_acceso']?date('d/m/Y H:i',strtotime($ud['ultimo_acceso'])):'Primera sesión' ?></p>
            </div>
        </div>
    </main>
</div>
</body>
</html>