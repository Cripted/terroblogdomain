<?php
require_once '../config/auth.php';
$auth->requireRole('editor');
$user = $auth->getCurrentUser();
$db   = getDB();

// ── Acciones rápidas (publicar/despublicar/eliminar) ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_id'])) {
        $id = (int)$_POST['toggle_id'];
        // Si se va a publicar y no tenía fecha, asignarla ahora
        $stmt = $db->prepare("SELECT publicado FROM articulos WHERE id = ?");
        $stmt->execute([$id]);
        $art = $stmt->fetch();
        if ($art) {
            if (!$art['publicado']) {
                $db->prepare("UPDATE articulos SET publicado = 1, fecha_publicacion = COALESCE(fecha_publicacion, NOW()) WHERE id = ?")
                   ->execute([$id]);
            } else {
                $db->prepare("UPDATE articulos SET publicado = 0 WHERE id = ?")
                   ->execute([$id]);
            }
        }
        setFlashMessage('success', 'Estado actualizado');
    } elseif (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $stmt = $db->prepare("SELECT imagen_destacada FROM articulos WHERE id = ?");
        $stmt->execute([$id]);
        $art = $stmt->fetch();
        if ($art) {
            $db->prepare("DELETE FROM articulo_tags WHERE articulo_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM articulos WHERE id = ?")->execute([$id]);
            if ($art['imagen_destacada']) {
                $path = UPLOAD_DIR . $art['imagen_destacada'];
                if (file_exists($path)) @unlink($path);
            }
            setFlashMessage('success', 'Artículo eliminado');
        }
    }
    redirect(SITE_URL . '/admin/articulos.php');
}

// ── Filtros ───────────────────────────────────────────────────────────────────
$filtroEstado    = $_GET['estado']    ?? 'todos';   // todos | publicado | borrador
$filtroCategoria = $_GET['categoria'] ?? '';
$busqueda        = trim($_GET['q']    ?? '');

$where  = [];
$params = [];

if ($filtroEstado === 'publicado') {
    $where[] = 'a.publicado = 1';
} elseif ($filtroEstado === 'borrador') {
    $where[] = 'a.publicado = 0';
}

if ($filtroCategoria) {
    $where[] = 'c.slug = ?';
    $params[] = $filtroCategoria;
}

if ($busqueda) {
    $where[] = '(a.titulo LIKE ? OR a.extracto LIKE ?)';
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

// Autores solo ven sus propios artículos; editores/admins ven todos
if (!$auth->hasRole('editor')) {
    $where[] = 'a.autor_id = ?';
    $params[] = $user['id'];
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$articulos = $db->prepare("
    SELECT a.id, a.titulo, a.slug, a.publicado, a.destacado,
           a.calificacion, a.vistas,
           a.fecha_publicacion, a.creado_en,
           c.nombre  AS categoria,
           u.nombre_completo AS autor
    FROM articulos a
    LEFT JOIN categorias c ON a.categoria_id = c.id
    LEFT JOIN usuarios   u ON a.autor_id     = u.id
    $whereSQL
    ORDER BY a.creado_en DESC
");
$articulos->execute($params);
$articulos = $articulos->fetchAll();

$categorias = $db->query("SELECT id, nombre, slug FROM categorias ORDER BY nombre")->fetchAll();

// Conteos para las pestañas
$counts = $db->query("
    SELECT
        COUNT(*)                          AS todos,
        SUM(publicado = 1)                AS publicados,
        SUM(publicado = 0)                AS borradores
    FROM articulos
    " . (!$auth->hasRole('editor') ? "WHERE autor_id = {$user['id']}" : "")
)->fetch();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artículos - Admin | Terror Digital</title>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Text:wght@400;600;700&family=Rubik:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .tab-bar {
            display: flex;
            gap: 0;
            border-bottom: 2px solid #222;
            margin-bottom: 1.5rem;
        }
        .tab-btn {
            padding: .65rem 1.4rem;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            color: #666;
            font-family: 'Rubik', sans-serif;
            font-size: .85rem;
            text-transform: uppercase;
            letter-spacing: .06rem;
            cursor: pointer;
            text-decoration: none;
            transition: all .2s;
            margin-bottom: -2px;
        }
        .tab-btn:hover { color: #ccc; }
        .tab-btn.active { color: var(--ghost-white); border-bottom-color: var(--blood-red); }
        .tab-count {
            display: inline-block;
            background: rgba(139,0,0,.3);
            color: #aaa;
            font-size: .72rem;
            padding: .1rem .45rem;
            border-radius: 2px;
            margin-left: .35rem;
        }
        .tab-btn.active .tab-count { background: var(--blood-red); color: #fff; }

        .search-bar {
            display: flex;
            gap: .75rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
            align-items: center;
        }
        .search-bar input[type="text"] {
            flex: 1;
            min-width: 200px;
            padding: .6rem 1rem;
            background: #0a0a0a;
            border: 1px solid #2a2a2a;
            color: var(--ghost-white);
            font-family: 'Rubik', sans-serif;
            font-size: .9rem;
        }
        .search-bar input:focus { outline: none; border-color: var(--blood-red); }
        .search-bar select {
            padding: .6rem 1rem;
            background: #0a0a0a;
            border: 1px solid #2a2a2a;
            color: var(--ghost-white);
            font-family: 'Rubik', sans-serif;
            font-size: .85rem;
        }

        .badge-draft     { background: rgba(255,180,0,.1);  color: #f5a623; border: 1px solid rgba(255,180,0,.3); }
        .badge-published { background: rgba(0,200,80,.1);   color: #0c6;    border: 1px solid rgba(0,200,80,.3);  }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #444;
        }
        .empty-state .empty-icon { font-size: 3rem; margin-bottom: 1rem; }
        .empty-state p { margin-bottom: 1.5rem; }
    </style>
</head>
<body>
<div class="grain"></div>
<div class="admin-layout">
    <?php include 'partials/sidebar.php'; ?>
    <main class="admin-main">

        <div class="admin-header">
            <div>
                <h1>📰 Artículos</h1>
                <p><?= count($articulos) ?> resultado<?= count($articulos) !== 1 ? 's' : '' ?></p>
            </div>
            <a href="nuevo_articulo.php" class="btn-primary">✏️ Nuevo artículo</a>
        </div>

        <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>

        <!-- Pestañas de estado -->
        <div class="tab-bar">
            <a class="tab-btn <?= $filtroEstado === 'todos'     ? 'active' : '' ?>"
               href="?estado=todos&categoria=<?= urlencode($filtroCategoria) ?>&q=<?= urlencode($busqueda) ?>">
                Todos <span class="tab-count"><?= $counts['todos'] ?></span>
            </a>
            <a class="tab-btn <?= $filtroEstado === 'publicado' ? 'active' : '' ?>"
               href="?estado=publicado&categoria=<?= urlencode($filtroCategoria) ?>&q=<?= urlencode($busqueda) ?>">
                Publicados <span class="tab-count"><?= $counts['publicados'] ?></span>
            </a>
            <a class="tab-btn <?= $filtroEstado === 'borrador'  ? 'active' : '' ?>"
               href="?estado=borrador&categoria=<?= urlencode($filtroCategoria) ?>&q=<?= urlencode($busqueda) ?>">
                Borradores <span class="tab-count"><?= (int)$counts['borradores'] ?></span>
            </a>
        </div>

        <!-- Buscador y filtro de categoría -->
        <form method="GET" class="search-bar">
            <input type="hidden" name="estado" value="<?= htmlspecialchars($filtroEstado) ?>">
            <input type="text" name="q" placeholder="Buscar por título o extracto..."
                   value="<?= htmlspecialchars($busqueda) ?>">
            <select name="categoria" onchange="this.form.submit()">
                <option value="">Todas las categorías</option>
                <?php foreach ($categorias as $cat): ?>
                <option value="<?= htmlspecialchars($cat['slug']) ?>"
                    <?= $filtroCategoria === $cat['slug'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['nombre']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-sm">Buscar</button>
            <?php if ($busqueda || $filtroCategoria): ?>
            <a href="?estado=<?= $filtroEstado ?>" class="btn-xs">✕ Limpiar</a>
            <?php endif; ?>
        </form>

        <!-- Tabla de artículos -->
        <?php if (empty($articulos)): ?>
        <div class="empty-state">
            <div class="empty-icon">📭</div>
            <p>No se encontraron artículos<?= $filtroEstado === 'borrador' ? ' guardados como borrador' : '' ?>.</p>
            <a href="nuevo_articulo.php" class="btn-primary">✏️ Crear el primero</a>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Título</th>
                    <th>Categoría</th>
                    <th>Autor</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                    <th>Vistas</th>
                    <th style="text-align:right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($articulos as $art): ?>
            <tr>
                <td>
                    <strong style="color:var(--ghost-white);"><?= htmlspecialchars($art['titulo']) ?></strong>
                    <?php if ($art['destacado']): ?>
                    <span style="font-size:.7rem;color:#f5a623;margin-left:.4rem;">⭐ Destacado</span>
                    <?php endif; ?>
                    <?php if ($art['calificacion']): ?>
                    <span style="font-size:.7rem;color:#888;margin-left:.4rem;">⭐ <?= number_format($art['calificacion'],1) ?></span>
                    <?php endif; ?>
                </td>
                <td style="color:#888;"><?= htmlspecialchars($art['categoria'] ?? '—') ?></td>
                <td style="color:#888;"><?= htmlspecialchars($art['autor'] ?? '—') ?></td>
                <td>
                    <span class="badge <?= $art['publicado'] ? 'badge-published' : 'badge-draft' ?>">
                        <?= $art['publicado'] ? '✅ Publicado' : '📝 Borrador' ?>
                    </span>
                </td>
                <td style="color:#666;font-size:.82rem;white-space:nowrap;">
                    <?php if ($art['fecha_publicacion']): ?>
                        <?= date('d/m/Y', strtotime($art['fecha_publicacion'])) ?>
                    <?php else: ?>
                        <span style="color:#444;">Sin publicar</span><br>
                        <span style="font-size:.75rem;">Creado: <?= date('d/m/Y', strtotime($art['creado_en'])) ?></span>
                    <?php endif; ?>
                </td>
                <td style="color:#888;"><?= number_format($art['vistas']) ?></td>
                <td style="white-space:nowrap;text-align:right;">
                    <a href="editar_articulo.php?id=<?= $art['id'] ?>" class="btn-xs">✏️ Editar</a>
                    <?php if ($art['publicado']): ?>
                        <a href="../pages/articulo.html?id=<?= htmlspecialchars($art['slug']) ?>"
                           target="_blank" class="btn-xs">👁️ Ver</a>
                    <?php endif; ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="toggle_id" value="<?= $art['id'] ?>">
                        <button type="submit" class="btn-xs">
                            <?= $art['publicado'] ? '📥 Borrador' : '🚀 Publicar' ?>
                        </button>
                    </form>
                    <form method="POST" style="display:inline"
                          onsubmit="return confirm('¿Eliminar «<?= htmlspecialchars(addslashes($art['titulo'])) ?>»? Esta acción no se puede deshacer.')">
                        <input type="hidden" name="delete_id" value="<?= $art['id'] ?>">
                        <button type="submit" class="btn-xs btn-danger">🗑️</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>

    </main>
</div>
</body>
</html>