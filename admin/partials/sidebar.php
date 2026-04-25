<?php $p = basename($_SERVER['PHP_SELF']); ?>
<aside class="admin-sidebar">
    <div class="sidebar-logo"><a href="../index.html">💀 TERROR DIGITAL</a></div>
    <nav class="sidebar-nav">
        <a href="index.php"          class="<?= $p==='index.php'?'active':'' ?>">🏠 Dashboard</a>
        <a href="articulos.php"      class="<?= in_array($p,['articulos.php','nuevo_articulo.php','editar_articulo.php'])?'active':'' ?>">📰 Artículos</a>
        <a href="nuevo_articulo.php" class="sub">➕ Nuevo artículo</a>
        <a href="discusiones.php"    class="<?= $p==='discusiones.php'?'active':'' ?>">💬 Discusiones</a>
        <?php if ($auth->hasRole('admin')): ?>
        <a href="usuarios.php"       class="<?= $p==='usuarios.php'?'active':'' ?>">👥 Usuarios</a>
        <a href="juegos.php"         class="<?= $p==='juegos.php'?'active':'' ?>">🎮 Juegos</a>
        <?php endif; ?>
        <a href="perfil.php"         class="<?= $p==='perfil.php'?'active':'' ?>">👤 Mi Perfil</a>
        <a href="logout.php" class="logout-link">🚪 Cerrar sesión</a>
    </nav>
    <div class="sidebar-user">
        <p><?= htmlspecialchars($user['nombre_completo']) ?></p>
        <small><?= strtoupper($user['rol']) ?></small>
    </div>
</aside>