<?php
require_once '../config/auth.php';
require_once '../config/auth.php';
$auth->requireLogin();
$user = $auth->getCurrentUser();
$db   = getDB();

// ── Helper: descarga imagen de Steam y la guarda en uploads/ ─────────────────
function downloadSteamImage(string $url, string $prefix = 'art'): array
{
    $allowed = [
        'cdn.akamai.steamstatic.com',
        'cdn.cloudflare.steamstatic.com',
        'steamcdn-a.akamaihd.net',
        'store.steampowered.com',
    ];
    $host = parse_url($url, PHP_URL_HOST);
    $ok   = false;
    foreach ($allowed as $a) {
        if ($host === $a || str_ends_with($host, '.' . $a)) { $ok = true; break; }
    }
    if (!$ok) return ['success' => false, 'message' => 'Dominio no permitido: ' . $host];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; TerrorDigital/1.0)',
    ]);
    $data     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$data || $httpCode !== 200) {
        return ['success' => false, 'message' => "No se pudo descargar la imagen (HTTP $httpCode)"];
    }

    $finfo  = new finfo(FILEINFO_MIME_TYPE);
    $mime   = $finfo->buffer($data);
    $extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    if (!isset($extMap[$mime])) return ['success' => false, 'message' => "Tipo no permitido: $mime"];

    if (strlen($data) > MAX_FILE_SIZE) return ['success' => false, 'message' => 'Imagen demasiado grande (máx 5 MB)'];

    if (!file_exists(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
    $filename = $prefix . '_steam_' . uniqid() . '_' . time() . '.' . $extMap[$mime];
    if (file_put_contents(UPLOAD_DIR . $filename, $data) === false) {
        return ['success' => false, 'message' => 'No se pudo guardar la imagen'];
    }
    return ['success' => true, 'filename' => $filename, 'url' => UPLOAD_URL . $filename];
}

$editId   = isset($_GET['id']) ? (int)$_GET['id'] : null;
$articulo = null;
$artTags  = [];
$errors   = [];

if ($editId) {
    $stmt = $db->prepare("SELECT * FROM articulos WHERE id = ?");
    $stmt->execute([$editId]);
    $articulo = $stmt->fetch();

    if (!$articulo) {
        setFlashMessage('error', 'Artículo no encontrado');
        redirect(SITE_URL . '/admin/articulos.php');
    }
    if (!$auth->hasRole('editor') && $articulo['autor_id'] != $user['id']) {
        setFlashMessage('error', 'Sin permiso para editar este artículo');
        redirect(SITE_URL . '/admin/articulos.php');
    }

    $tStmt = $db->prepare("SELECT tag_id FROM articulo_tags WHERE articulo_id = ?");
    $tStmt->execute([$editId]);
    $artTags = array_column($tStmt->fetchAll(), 'tag_id');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo      = sanitize($_POST['titulo']      ?? '');
    $extracto    = sanitize($_POST['extracto']    ?? '');
    $contenido   = $_POST['contenido'] ?? '';
    $categoriaId = (int)($_POST['categoria_id']  ?? 0);
    $juegoId     = !empty($_POST['juego_id'])     ? (int)$_POST['juego_id'] : null;
    $calificacion= !empty($_POST['calificacion']) ? (float)$_POST['calificacion'] : null;
    $destacado   = isset($_POST['destacado'])     ? 1 : 0;
    $publicado   = isset($_POST['publicado'])     ? 1 : 0;
    $tags        = $_POST['tags'] ?? [];
    $slug        = generateSlug($titulo);

    if (!$titulo)      $errors[] = 'El título es requerido';
    if (!$contenido)   $errors[] = 'El contenido es requerido';
    if (!$categoriaId) $errors[] = 'La categoría es requerida';

    // ── Prioridad de imagen: Steam URL → archivo local → imagen previa ────────
    $imagen = $articulo['imagen_destacada'] ?? null;

    if (!empty($_POST['steam_image_url'])) {
        // Descargar imagen de Steam y guardarla localmente
        $steamUrl = trim($_POST['steam_image_url']);
        $download = downloadSteamImage($steamUrl, 'art');
        if ($download['success']) {
            // Si había una imagen local previa, borrarla para no acumular archivos
            if ($imagen && !str_starts_with($imagen, 'http')) {
                $oldPath = UPLOAD_DIR . $imagen;
                if (file_exists($oldPath)) @unlink($oldPath);
            }
            $imagen = $download['filename'];
        } else {
            $errors[] = 'Error al descargar imagen de Steam: ' . $download['message'];
        }
    } elseif (!empty($_FILES['imagen']['name'])) {
        $upload = uploadImage($_FILES['imagen'], 'art');
        if ($upload['success']) {
            // Borrar imagen local previa si existe
            if ($imagen && !str_starts_with($imagen, 'http')) {
                $oldPath = UPLOAD_DIR . $imagen;
                if (file_exists($oldPath)) @unlink($oldPath);
            }
            $imagen = $upload['filename'];
        } else {
            $errors[] = $upload['message'];
        }
    }

    if (empty($errors)) {
        $juegoIdVal = $juegoId ?: null;
        $calificVal = $calificacion ? (float)$calificacion : null;

        if ($editId) {
            $stmt = $db->prepare("
                UPDATE articulos SET
                    titulo=?, slug=?, extracto=?, contenido=?,
                    imagen_destacada=?, categoria_id=?, juego_id=?,
                    calificacion=?, destacado=?, publicado=?,
                    fecha_publicacion=IF(?=1 AND fecha_publicacion IS NULL,NOW(),fecha_publicacion)
                WHERE id=?
            ");
            $stmt->execute([
                $titulo, $slug, $extracto, $contenido,
                $imagen, $categoriaId, $juegoIdVal,
                $calificVal, $destacado, $publicado,
                $publicado, $editId
            ]);
            $artId = $editId;
        } else {
            $stmt = $db->prepare("
                INSERT INTO articulos
                    (titulo,slug,extracto,contenido,imagen_destacada,
                     autor_id,categoria_id,juego_id,calificacion,
                     destacado,publicado,fecha_publicacion)
                VALUES(?,?,?,?,?,?,?,?,?,?,?,IF(?=1,NOW(),NULL))
            ");
            $stmt->execute([
                $titulo, $slug, $extracto, $contenido, $imagen,
                $user['id'], $categoriaId, $juegoIdVal,
                $calificVal, $destacado, $publicado, $publicado
            ]);
            $artId = $db->lastInsertId();
        }

        $del = $db->prepare("DELETE FROM articulo_tags WHERE articulo_id=?");
        $del->execute([$artId]);
        foreach ($tags as $tagId) {
            $ins = $db->prepare("INSERT IGNORE INTO articulo_tags (articulo_id,tag_id) VALUES(?,?)");
            $ins->execute([$artId, $tagId]);
        }

        setFlashMessage('success', $editId ? 'Artículo actualizado' : 'Artículo creado correctamente');
        redirect(SITE_URL . '/admin/articulos.php');
    }
}

$categorias = $db->query("SELECT * FROM categorias WHERE activo=1 ORDER BY nombre")->fetchAll();
$juegos     = $db->query("SELECT * FROM juegos     WHERE activo=1 ORDER BY nombre")->fetchAll();
$todosTags  = $db->query("SELECT * FROM tags ORDER BY nombre")->fetchAll();
$flash = getFlashMessage();

$imagenActual    = $articulo['imagen_destacada'] ?? null;
$imagenEsExterna = $imagenActual &&
    (str_starts_with($imagenActual,'http://') || str_starts_with($imagenActual,'https://'));
$imagenSrc = $imagenActual
    ? ($imagenEsExterna ? $imagenActual : '../uploads/'.$imagenActual)
    : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $editId ? 'Editar' : 'Nuevo' ?> Artículo - Admin | Terror Digital</title>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Text:wght@400;600;700&family=Rubik:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .img-source-tabs { display:flex; margin-bottom:.8rem; }
        .img-source-tab {
            flex:1; padding:.55rem; text-align:center;
            background:#0a0a0a; border:1px solid #222;
            color:#666; font-family:'Rubik',sans-serif;
            font-size:.78rem; text-transform:uppercase;
            letter-spacing:.05rem; cursor:pointer; transition:all .2s;
        }
        .img-source-tab.active { background:rgba(139,0,0,.2); border-color:var(--blood-red); color:var(--ghost-white); }
        .img-panel { display:none; }
        .img-panel.active { display:block; }

        .steam-search-box { background:rgba(10,10,10,.8); border:1px solid #1b2838; padding:1rem; }
        .steam-search-box h4 { color:#66c0f4; font-family:'Rubik',sans-serif; font-size:.82rem; text-transform:uppercase; letter-spacing:.06rem; margin-bottom:.7rem; }
        .steam-input-row { display:flex; gap:.5rem; }
        .steam-input-row input { flex:1; padding:.55rem .8rem; background:#1b2838; border:1px solid #2a475e; color:#c6d4df; font-family:'Rubik',sans-serif; font-size:.85rem; }
        .steam-input-row input:focus { outline:none; border-color:#66c0f4; }
        .btn-steam { background:#1b2838; border:1px solid #66c0f4; color:#66c0f4; padding:.55rem 1rem; font-family:'Rubik',sans-serif; font-size:.8rem; cursor:pointer; white-space:nowrap; transition:all .2s; }
        .btn-steam:hover { background:#2a475e; }
        .btn-steam:disabled { opacity:.5; cursor:not-allowed; }

        #art-steam-status { font-family:'Rubik',sans-serif; font-size:.76rem; color:#888; margin-top:.4rem; min-height:1.1em; }
        #art-steam-status.error { color:#f66; }
        #art-steam-status.ok { color:#6f6; }

        #art-steam-results { display:none; margin-top:.8rem; max-height:280px; overflow-y:auto; border:1px solid #2a475e; }
        .steam-result-item { display:flex; align-items:center; gap:.7rem; padding:.5rem .7rem; cursor:pointer; border-bottom:1px solid #1b2838; transition:background .15s; }
        .steam-result-item:last-child { border-bottom:none; }
        .steam-result-item:hover { background:#2a475e; }
        .steam-result-item.selected { background:rgba(102,192,244,.12); border-left:3px solid #66c0f4; }
        .steam-result-item img { width:72px; height:34px; object-fit:cover; flex-shrink:0; background:#0a0a0a; }
        .steam-result-info { flex:1; min-width:0; }
        .steam-result-info strong { display:block; color:#c6d4df; font-family:'Rubik',sans-serif; font-size:.82rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .steam-result-info small { color:#66c0f4; font-size:.7rem; font-family:'Rubik',sans-serif; }
        .steam-check { color:#66c0f4; font-size:1rem; flex-shrink:0; opacity:0; }
        .steam-result-item.selected .steam-check { opacity:1; }

        #art-steam-preview { display:none; margin-top:.7rem; padding:.7rem; background:rgba(102,192,244,.07); border:1px solid #66c0f4; align-items:center; gap:.8rem; }
        #art-steam-preview img { width:110px; height:52px; object-fit:cover; border:1px solid #2a475e; }
        #art-steam-preview p { color:#c6d4df; font-family:'Rubik',sans-serif; font-size:.82rem; flex:1; }
        #art-steam-preview button { background:transparent; border:1px solid #c44; color:#c44; padding:.3rem .6rem; font-size:.75rem; cursor:pointer; }
        #art-steam-preview button:hover { background:rgba(200,50,50,.2); }

        .steam-note { font-family:'Rubik',sans-serif; font-size:.72rem; color:#4a8; margin-top:.4rem; }

        .current-img-wrap { margin-bottom:.8rem; padding:.6rem; background:#0a0a0a; border:1px solid #222; transition:opacity .2s; }
        .current-img-wrap img { width:100%; max-height:140px; object-fit:cover; display:block; border:1px solid #333; }
        .current-img-label { font-family:'Rubik',sans-serif; font-size:.72rem; color:#555; margin-top:.4rem; word-break:break-all; }
    </style>
</head>
<body>
<div class="grain"></div>
<div class="admin-layout">
    <?php include 'partials/sidebar.php'; ?>
    <main class="admin-main">
        <div class="admin-header">
            <h1><?= $editId ? '✏️ Editar Artículo' : '➕ Nuevo Artículo' ?></h1>
            <a href="articulos.php" class="btn-sm">← Volver</a>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $e): ?><p>• <?= htmlspecialchars($e) ?></p><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="admin-form">
            <input type="hidden" name="steam_image_url" id="art-steam-url">

            <div class="form-row">
                <div class="form-col-main">
                    <div class="form-group">
                        <label>Título *</label>
                        <input type="text" name="titulo" required
                               value="<?= htmlspecialchars($articulo['titulo'] ?? $_POST['titulo'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Extracto / Resumen</label>
                        <textarea name="extracto" rows="3"><?= htmlspecialchars($articulo['extracto'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Contenido * <small style="color:#555">(HTML: &lt;p&gt; &lt;h2&gt; &lt;ul&gt; &lt;strong&gt; …)</small></label>
                        <textarea name="contenido" rows="22" class="content-editor"><?= htmlspecialchars($articulo['contenido'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="form-col-side">

                    <div class="form-box">
                        <h3>Publicación</h3>
                        <label class="checkbox-label">
                            <input type="checkbox" name="publicado" <?= ($articulo['publicado'] ?? false) ? 'checked' : '' ?>>
                            Publicar artículo
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="destacado" <?= ($articulo['destacado'] ?? false) ? 'checked' : '' ?>>
                            ⭐ Artículo destacado
                        </label>
                        <div style="margin-top:1rem;">
                            <button type="submit" class="btn-primary" style="width:100%">
                                <?= $editId ? '💾 Guardar cambios' : '✅ Crear artículo' ?>
                            </button>
                        </div>
                    </div>

                    <div class="form-box">
                        <h3>Categoría *</h3>
                        <select name="categoria_id" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>"
                                <?= ($articulo['categoria_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-box">
                        <h3>Juego relacionado</h3>
                        <select name="juego_id">
                            <option value="">Ninguno</option>
                            <?php foreach ($juegos as $j): ?>
                            <option value="<?= $j['id'] ?>"
                                <?= ($articulo['juego_id'] ?? 0) == $j['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($j['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-box">
                        <h3>Calificación (0–10)</h3>
                        <input type="number" name="calificacion" min="0" max="10" step="0.1"
                               value="<?= $articulo['calificacion'] ?? '' ?>" placeholder="Ej: 9.5">
                    </div>

                    <!-- ── Imagen destacada ─────────────────── -->
                    <div class="form-box">
                        <h3>Imagen destacada</h3>

                        <?php if ($imagenSrc): ?>
                        <div class="current-img-wrap" id="current-img-wrap">
                            <img src="<?= htmlspecialchars($imagenSrc) ?>" alt="Imagen actual"
                                 onerror="this.alt='No se pudo cargar';this.style.display='none'">
                            <p class="current-img-label">
                                <?= $imagenEsExterna ? '🔗 Steam/externa' : '📁 Local' ?>:
                                <code><?= htmlspecialchars($imagenActual) ?></code>
                            </p>
                        </div>
                        <p style="color:#555;font-family:'Rubik',sans-serif;font-size:.72rem;margin-bottom:.6rem;">
                            Selecciona una nueva imagen para reemplazar la actual:
                        </p>
                        <?php else: ?>
                        <p style="color:#555;font-family:'Rubik',sans-serif;font-size:.72rem;margin-bottom:.6rem;">
                            Selecciona una imagen:
                        </p>
                        <?php endif; ?>

                        <div class="img-source-tabs">
                            <div class="img-source-tab active" onclick="switchArtImgTab('steam',this)">🎮 Steam</div>
                            <div class="img-source-tab" onclick="switchArtImgTab('local',this)">📁 Local</div>
                        </div>

                        <div class="img-panel active" id="art-panel-steam">
                            <div class="steam-search-box">
                                <h4>🔵 Buscar en Steam</h4>
                                <div class="steam-input-row">
                                    <input type="text" id="art-steam-query" placeholder="Nombre del juego..."
                                           onkeydown="if(event.key==='Enter'){event.preventDefault();buscarArtSteam();}">
                                    <button type="button" class="btn-steam" id="art-btn-steam" onclick="buscarArtSteam()">🔍 Buscar</button>
                                </div>
                                <div id="art-steam-status"></div>
                                <div id="art-steam-results"></div>
                                <div id="art-steam-preview">
                                    <img id="art-preview-img" src="" alt="">
                                    <p id="art-preview-nombre"></p>
                                    <button type="button" onclick="limpiarArtSeleccion()">✕ Quitar</button>
                                </div>
                                <p class="steam-note" id="art-steam-note" style="display:none;">
                                    ✅ La imagen se descargará y guardará en el servidor al guardar el artículo.
                                </p>
                            </div>
                        </div>

                        <div class="img-panel" id="art-panel-local">
                            <input type="file" name="imagen" accept=".jpg,.jpeg,.png,.gif,.webp"
                                   id="art-local-input" onchange="previewArtLocal(this)">
                            <small class="hint-txt">JPG, PNG, GIF, WebP. Máx 5 MB.</small>
                            <img id="art-local-preview" src="" alt=""
                                 style="display:none;width:100%;max-height:120px;object-fit:cover;margin-top:.5rem;border:1px solid #333;">
                        </div>
                    </div>
                    <!-- ── /Imagen destacada ─────────────────── -->

                    <div class="form-box">
                        <h3>Tags</h3>
                        <?php foreach ($todosTags as $tag): ?>
                        <label class="checkbox-label">
                            <input type="checkbox" name="tags[]" value="<?= $tag['id'] ?>"
                                <?= in_array($tag['id'], $artTags) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($tag['nombre']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>

                </div>
            </div>
        </form>
    </main>
</div>

<script>
function switchArtImgTab(tab, el) {
    document.querySelectorAll('.img-source-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.img-panel').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('art-panel-' + tab).classList.add('active');
    if (tab === 'local') {
        document.getElementById('art-steam-url').value = '';
        limpiarArtSeleccion(false);
    } else {
        document.getElementById('art-local-input').value = '';
        document.getElementById('art-local-preview').style.display = 'none';
    }
}

function previewArtLocal(input) {
    const prev = document.getElementById('art-local-preview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { prev.src = e.target.result; prev.style.display = 'block'; };
        reader.readAsDataURL(input.files[0]);
        document.getElementById('art-steam-url').value = '';
        limpiarArtSeleccion(false);
    }
}

async function buscarArtSteam() {
    const query   = document.getElementById('art-steam-query').value.trim();
    if (!query) return;
    const btn     = document.getElementById('art-btn-steam');
    const status  = document.getElementById('art-steam-status');
    const results = document.getElementById('art-steam-results');

    btn.disabled = true; btn.textContent = 'Buscando...';
    status.className = ''; status.textContent = 'Consultando Steam...';
    results.style.display = 'none'; results.innerHTML = '';

    try {
        const res  = await fetch(`../php/steam_proxy.php?q=${encodeURIComponent(query)}`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data  = await res.json();
        const items = data?.items ?? [];

        if (!items.length) {
            status.className = 'error';
            status.textContent = 'No se encontraron juegos con ese nombre.';
            btn.disabled = false; btn.textContent = '🔍 Buscar'; return;
        }

        status.className = 'ok';
        status.textContent = `${items.length} resultado(s). Haz clic para seleccionar.`;

        results.innerHTML = items.slice(0, 15).map(item => {
            const img        = `https://cdn.akamai.steamstatic.com/steam/apps/${item.id}/header.jpg`;
            const imgPreview = `../php/image_proxy.php?url=${encodeURIComponent(img)}`;
            return `<div class="steam-result-item"
                        onclick="seleccionarArtSteam('${escJS(img)}','${escJS(item.name)}')"
                        data-url="${escHTML(img)}">
                 <img src="${escHTML(imgPreview)}" alt="${escHTML(item.name)}"
                     onerror="this.src='https://placehold.co/72x34/1b2838/66c0f4?text=?'">
                <div class="steam-result-info">
                    <strong>${escHTML(item.name)}</strong>
                    <small>App ID: ${item.id}</small>
                </div>
                <span class="steam-check">✔</span>
            </div>`;
        }).join('');
        results.style.display = 'block';
    } catch (err) {
        status.className = 'error';
        status.textContent = 'Error al buscar. Verifica que php/steam_proxy.php existe.';
        console.error(err);
    }
    btn.disabled = false; btn.textContent = '🔍 Buscar';
}

function seleccionarArtSteam(imgUrl, nombre) {
    document.querySelectorAll('#art-steam-results .steam-result-item').forEach(el => {
        el.classList.toggle('selected', el.dataset.url === imgUrl);
    });
    document.getElementById('art-steam-url').value = imgUrl;

    // Preview usando el proxy solo para visualizar; el PHP descargará la imagen real al guardar
    document.getElementById('art-preview-img').src = `../php/image_proxy.php?url=${encodeURIComponent(imgUrl)}`;
    document.getElementById('art-preview-nombre').textContent = nombre;
    document.getElementById('art-steam-preview').style.display = 'flex';
    document.getElementById('art-steam-note').style.display = 'block';

    const cw = document.getElementById('current-img-wrap');
    if (cw) cw.style.opacity = '.4';
    document.getElementById('art-local-input').value = '';
    document.getElementById('art-local-preview').style.display = 'none';
}

function limpiarArtSeleccion(resetField = true) {
    if (resetField) document.getElementById('art-steam-url').value = '';
    document.querySelectorAll('#art-steam-results .steam-result-item').forEach(el => el.classList.remove('selected'));
    document.getElementById('art-steam-preview').style.display = 'none';
    document.getElementById('art-steam-note').style.display = 'none';
    const cw = document.getElementById('current-img-wrap');
    if (cw) cw.style.opacity = '1';
}

function escHTML(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function escJS(s)   { return String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'\\"'); }
</script>
</body>
</html>