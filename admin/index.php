<?php
require_once '../config/auth.php';
$auth->requireRole('editor');
$user  = $auth->getCurrentUser();
$flash = getFlashMessage();

try {
    $db = getDB();
    $totalArticulos  = $db->query("SELECT COUNT(*) FROM articulos  WHERE publicado = TRUE")->fetchColumn();
    $totalBorradores = $db->query("SELECT COUNT(*) FROM articulos  WHERE publicado = FALSE")->fetchColumn();
    $totalDiscus     = $db->query("SELECT COUNT(*) FROM discusiones WHERE aprobado = TRUE")->fetchColumn();
    $totalUsuarios   = $db->query("SELECT COUNT(*) FROM usuarios   WHERE activo = TRUE")->fetchColumn();
} catch (Exception $e) {
    $totalArticulos = $totalBorradores = $totalDiscus = $totalUsuarios = '—';
}

try {
    $ultimosArticulos = $db->query("
        SELECT a.id, a.titulo, a.slug, a.publicado, a.fecha_publicacion, a.vistas,
               c.nombre as categoria, u.nombre_completo as autor
        FROM articulos a
        LEFT JOIN categorias c ON a.categoria_id = c.id
        LEFT JOIN usuarios   u ON a.autor_id = u.id
        ORDER BY a.fecha_publicacion DESC
        LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {
    $ultimosArticulos = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin | TERROR DIGITAL</title>
    <link href="https://fonts.googleapis.com/css2?family=Creepster&family=Nosifer&family=Crimson+Text:wght@400;600;700&family=Rubik:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
<div class="grain"></div>

<div class="admin-layout">
    <?php include 'partials/sidebar.php'; ?>

    <main class="admin-main">

        <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>

        <div class="admin-header">
            <div>
                <h1>🏠 Dashboard</h1>
                <p>Bienvenido, <?= htmlspecialchars($user['nombre_completo'] ?? $user['username']) ?></p>
            </div>
            <a href="../index.html" target="_blank" class="btn-sm">Ver sitio →</a>
        </div>

        <!-- Estadísticas -->
        <div class="admin-stats">
            <div class="admin-stat-card">
                <div class="big"><?= $totalArticulos ?></div>
                <div class="label">Artículos publicados</div>
            </div>
            <div class="admin-stat-card">
                <div class="big"><?= $totalBorradores ?></div>
                <div class="label">Borradores</div>
            </div>
            <div class="admin-stat-card">
                <div class="big"><?= $totalDiscus ?></div>
                <div class="label">Discusiones activas</div>
            </div>
            <div class="admin-stat-card">
                <div class="big"><?= $totalUsuarios ?></div>
                <div class="label">Usuarios activos</div>
            </div>
        </div>

        <!-- Acciones rápidas -->
        <div class="admin-panel">
            <h3>Acciones Rápidas</h3>
            <div style="display:flex;gap:1rem;flex-wrap:wrap;">
                <a href="nuevo_articulo.php" class="btn btn-primary">✏️ Escribir artículo</a>
                <a href="articulos.php"      class="btn btn-secondary">📋 Ver artículos</a>
                <a href="discusiones.php"    class="btn btn-secondary">💬 Moderar discusiones</a>
                <?php if ($auth->hasRole('admin')): ?>
                <a href="usuarios.php"       class="btn btn-secondary">👥 Gestionar usuarios</a>
                <a href="juegos.php"         class="btn btn-secondary">🎮 Gestionar juegos</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Últimos artículos -->
        <div class="admin-panel">
            <h3>Últimos Artículos</h3>
            <?php if (empty($ultimosArticulos)): ?>
                <p style="color:#999;">No hay artículos todavía. <a href="nuevo_articulo.php" style="color:var(--accent-crimson);">Crea el primero →</a></p>
            <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Categoría</th>
                            <th>Autor</th>
                            <th>Fecha</th>
                            <th>Vistas</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($ultimosArticulos as $art): ?>
                        <tr>
                            <td><?= htmlspecialchars($art['titulo']) ?></td>
                            <td><?= htmlspecialchars($art['categoria'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($art['autor'] ?? '—') ?></td>
                            <td><?= $art['fecha_publicacion'] ? date('d/m/Y', strtotime($art['fecha_publicacion'])) : '—' ?></td>
                            <td><?= number_format($art['vistas']) ?></td>
                            <td>
                                <span class="badge <?= $art['publicado'] ? 'badge-published' : 'badge-draft' ?>">
                                    <?= $art['publicado'] ? 'Publicado' : 'Borrador' ?>
                                </span>
                            </td>
                            <td style="white-space:nowrap;">
                                <a href="editar_articulo.php?id=<?= $art['id'] ?>" class="btn btn-sm btn-secondary">✏️ Editar</a>
                                <?php if ($art['publicado']): ?>
                                <a href="../pages/articulo.html?id=<?= htmlspecialchars($art['slug']) ?>" target="_blank" class="btn btn-sm btn-secondary">👁️ Ver</a>
                                <?php endif; ?>
                                <button onclick="eliminarArticulo(<?= $art['id'] ?>, this)" class="btn btn-sm btn-danger">🗑️</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<script>
function eliminarArticulo(id, btn) {
    if (!confirm('¿Eliminar este artículo? Esta acción no se puede deshacer.')) return;
    btn.disabled = true;
    btn.textContent = '...';

    fetch('ajax/eliminar_articulo.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.closest('tr').remove();
        } else {
            alert(data.message || 'Error al eliminar');
            btn.disabled = false;
            btn.textContent = '🗑️';
        }
    })
    .catch(() => {
        alert('Error de conexión');
        btn.disabled = false;
        btn.textContent = '🗑️';
    });
}
</script>
</body>
</html>