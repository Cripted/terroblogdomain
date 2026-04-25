<?php
require_once '../config/auth.php';
$auth->requireLogin();
$user = $auth->getCurrentUser();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $auth->requireRole('editor');
    if (isset($_POST['delete_id'])) {
        $s=$conn->prepare("DELETE FROM discusiones WHERE id=?"); $s->bind_param("i",(int)$_POST['delete_id']); $s->execute(); $s->close();
        setFlashMessage('success','Discusión eliminada');
    } elseif (isset($_POST['toggle_id'])) {
        $s=$conn->prepare("UPDATE discusiones SET aprobado=NOT aprobado WHERE id=?"); $s->bind_param("i",(int)$_POST['toggle_id']); $s->execute(); $s->close();
        setFlashMessage('success','Estado actualizado');
    }
    redirect(SITE_URL.'/admin/discusiones.php');
}

$fj = sanitize($_GET['juego'] ?? '');
if ($fj) {
    $stmt=$conn->prepare("SELECT d.id,d.titulo,d.autor_nombre,d.aprobado,d.likes,d.fecha_creacion,j.nombre AS juego,(SELECT COUNT(*) FROM comentarios_discusion cd WHERE cd.discusion_id=d.id) AS total_comentarios FROM discusiones d JOIN juegos j ON d.juego_id=j.id WHERE j.slug=? ORDER BY d.fecha_creacion DESC");
    $stmt->bind_param("s",$fj);
} else {
    $stmt=$conn->prepare("SELECT d.id,d.titulo,d.autor_nombre,d.aprobado,d.likes,d.fecha_creacion,j.nombre AS juego,(SELECT COUNT(*) FROM comentarios_discusion cd WHERE cd.discusion_id=d.id) AS total_comentarios FROM discusiones d JOIN juegos j ON d.juego_id=j.id ORDER BY d.fecha_creacion DESC");
}
$stmt->execute();
$discs=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
$juegos=$conn->query("SELECT * FROM juegos WHERE activo=1 ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
$flash=getFlashMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discusiones - Admin | Terror Digital</title>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Text:wght@400;600;700&family=Rubik:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css"><link rel="stylesheet" href="../css/admin.css">
</head>
<body>
<div class="grain"></div>
<div class="admin-layout">
    <?php include 'partials/sidebar.php'; ?>
    <main class="admin-main">
        <div class="admin-header"><h1>💬 Discusiones</h1></div>
        <?php if ($flash): ?><div class="alert alert-<?= $flash['type']==='error'?'error':'success' ?>"><?= htmlspecialchars($flash['message']) ?></div><?php endif; ?>
        <form method="GET" class="filter-bar">
            <select name="juego" onchange="this.form.submit()">
                <option value="">Todos los juegos</option>
                <?php foreach($juegos as $j): ?><option value="<?= $j['slug'] ?>" <?= $fj===$j['slug']?'selected':'' ?>><?= htmlspecialchars($j['nombre']) ?></option><?php endforeach; ?>
            </select>
            <a href="discusiones.php" class="btn-xs">Ver todas</a>
        </form>
        <p style="color:#666;margin-bottom:1rem;font-size:.9rem;"><?= count($discs) ?> discusiones</p>
        <table class="admin-table">
            <thead><tr><th>Título</th><th>Autor</th><th>Juego</th><th>Resp.</th><th>Likes</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach($discs as $d): ?>
            <tr>
                <td><?= htmlspecialchars($d['titulo']) ?></td>
                <td><?= htmlspecialchars($d['autor_nombre']) ?></td>
                <td><?= htmlspecialchars($d['juego']) ?></td>
                <td>💬 <?= $d['total_comentarios'] ?></td>
                <td>❤️ <?= $d['likes'] ?></td>
                <td><span class="badge <?= $d['aprobado']?'badge-green':'badge-gray' ?>"><?= $d['aprobado']?'Visible':'Oculta' ?></span></td>
                <td><?= date('d/m/Y',strtotime($d['fecha_creacion'])) ?></td>
                <td class="actions-cell">
                    <?php if($auth->hasRole('editor')): ?>
                    <form method="POST" style="display:inline"><input type="hidden" name="toggle_id" value="<?= $d['id'] ?>"><button type="submit" class="btn-xs"><?= $d['aprobado']?'🚫 Ocultar':'✅ Aprobar' ?></button></form>
                    <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar?')"><input type="hidden" name="delete_id" value="<?= $d['id'] ?>"><button type="submit" class="btn-xs btn-danger">🗑️</button></form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</div>
</body>
</html>