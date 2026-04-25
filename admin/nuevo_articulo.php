<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Artículo - Admin Terror Digital</title>
    <link href="https://fonts.googleapis.com/css2?family=Creepster&family=Nosifer&family=Crimson+Text:wght@400;600;700&family=Rubik:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
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

        .steam-search-box { background:rgba(10,10,10,.8); border:1px solid #1b2838; padding:1rem; margin-top:.4rem; }
        .steam-search-box h4 { color:#66c0f4; font-family:'Rubik',sans-serif; font-size:.82rem; text-transform:uppercase; letter-spacing:.06rem; margin-bottom:.7rem; }
        .steam-input-row { display:flex; gap:.5rem; }
        .steam-input-row input { flex:1; padding:.55rem .8rem; background:#1b2838; border:1px solid #2a475e; color:#c6d4df; font-family:'Rubik',sans-serif; font-size:.85rem; }
        .steam-input-row input:focus { outline:none; border-color:#66c0f4; }
        .btn-steam { background:#1b2838; border:1px solid #66c0f4; color:#66c0f4; padding:.55rem 1rem; font-family:'Rubik',sans-serif; font-size:.8rem; cursor:pointer; white-space:nowrap; transition:all .2s; }
        .btn-steam:hover { background:#2a475e; }
        .btn-steam:disabled { opacity:.5; cursor:not-allowed; }

        #nart-steam-status { font-family:'Rubik',sans-serif; font-size:.76rem; color:#888; margin-top:.4rem; min-height:1.1em; }
        #nart-steam-status.error { color:#f66; }
        #nart-steam-status.ok { color:#6f6; }

        #nart-steam-results { display:none; margin-top:.8rem; max-height:260px; overflow-y:auto; border:1px solid #2a475e; }
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

        #nart-steam-preview { display:none; margin-top:.7rem; padding:.7rem; background:rgba(102,192,244,.07); border:1px solid #66c0f4; align-items:center; gap:.8rem; }
        #nart-steam-preview img { width:110px; height:52px; object-fit:cover; border:1px solid #2a475e; }
        #nart-steam-preview p { color:#c6d4df; font-family:'Rubik',sans-serif; font-size:.82rem; flex:1; }
        #nart-steam-preview button { background:transparent; border:1px solid #c44; color:#c44; padding:.3rem .6rem; font-size:.75rem; cursor:pointer; }
        #nart-steam-preview button:hover { background:rgba(200,50,50,.2); }

        .steam-note { font-family:'Rubik',sans-serif; font-size:.72rem; color:#4a8; margin-top:.4rem; }
    </style>
</head>
<body>
<div class="grain"></div>

<?php
require_once '../config/auth.php';
$auth->requireRole('editor');
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

$errorMsg   = '';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo       = sanitize($_POST['titulo']       ?? '');
    $extracto     = sanitize($_POST['extracto']     ?? '');
    $contenido    = $_POST['contenido']             ?? '';
    $categoria_id = intval($_POST['categoria_id']   ?? 0);
    $juego_id     = intval($_POST['juego_id']       ?? 0);
    $calificacion = floatval($_POST['calificacion'] ?? 0);
    $publicado    = isset($_POST['publicado']) ? 1 : 0;
    $destacado    = isset($_POST['destacado']) ? 1 : 0;
    $tags_raw     = sanitize($_POST['tags']         ?? '');
    $slug         = generateSlug($titulo);
    $autor_id     = $user['id'];

    if (empty($titulo) || empty($contenido) || !$categoria_id) {
        $errorMsg = 'Título, contenido y categoría son obligatorios.';
    } else {
        $check = $db->prepare("SELECT id FROM articulos WHERE slug = ?");
        $check->execute([$slug]);
        if ($check->fetch()) $slug .= '-' . time();

        // ── Prioridad: URL Steam → archivo local ──────────────────────────────
        $imagen_destacada = null;

        if (!empty($_POST['steam_image_url'])) {
            // Descargar imagen de Steam y guardarla localmente
            $download = downloadSteamImage(trim($_POST['steam_image_url']), 'art');
            if ($download['success']) {
                $imagen_destacada = $download['filename'];
            } else {
                $errorMsg = 'Error al descargar imagen de Steam: ' . $download['message'];
            }
        } elseif (!empty($_FILES['imagen']['name'])) {
            $up = uploadImage($_FILES['imagen'], 'art');
            if ($up['success']) {
                $imagen_destacada = $up['filename'];
            } else {
                $errorMsg = $up['message'];
            }
        }

        if (!$errorMsg) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO articulos
                        (titulo, slug, extracto, contenido, imagen_destacada,
                         categoria_id, juego_id, autor_id, calificacion,
                         publicado, destacado, fecha_publicacion)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                        " . ($publicado ? 'NOW()' : 'NULL') . ")
                ");
                $stmt->execute([
                    $titulo, $slug, $extracto, $contenido, $imagen_destacada,
                    $categoria_id, $juego_id ?: null, $autor_id, $calificacion,
                    $publicado, $destacado
                ]);
                $newId = $db->lastInsertId();

                if ($tags_raw) {
                    $tags = array_map('trim', explode(',', $tags_raw));
                    foreach ($tags as $tagNombre) {
                        if (!$tagNombre) continue;
                        $db->prepare("INSERT IGNORE INTO tags (nombre, slug) VALUES (?, ?)")
                           ->execute([$tagNombre, generateSlug($tagNombre)]);
                        $tagId = $db->query("SELECT id FROM tags WHERE slug = '" . generateSlug($tagNombre) . "'")->fetchColumn();
                        if ($tagId) {
                            $db->prepare("INSERT IGNORE INTO articulo_tags (articulo_id, tag_id) VALUES (?, ?)")
                               ->execute([$newId, $tagId]);
                        }
                    }
                }

                setFlashMessage('success', "Artículo \"$titulo\" " . ($publicado ? 'publicado' : 'guardado como borrador') . " correctamente.");
                redirect(SITE_URL . '/admin/articulos.php');

            } catch (PDOException $e) {
                $errorMsg = 'Error al guardar: ' . $e->getMessage();
            }
        }
    }
}

$categorias = $db->query("SELECT id, nombre FROM categorias ORDER BY nombre")->fetchAll();
$juegos     = $db->query("SELECT id, nombre FROM juegos WHERE activo = TRUE ORDER BY nombre")->fetchAll();
?>

<div class="admin-wrapper">
    <div class="admin-topbar">
        <a href="index.php" class="logo-small">TERROR DIGITAL</a>
        <div class="user-info">
            <span>👤 <?= htmlspecialchars($user['nombre_completo'] ?? $user['username']) ?></span>
            <a href="logout.php" class="btn-logout">Cerrar sesión</a>
        </div>
    </div>

    <nav class="admin-nav">
        <a href="index.php">Dashboard</a>
        <a href="articulos.php">Artículos</a>
        <a href="nuevo_articulo.php" class="active">+ Nuevo artículo</a>
        <a href="discusiones.php">Discusiones</a>
        <?php if ($auth->hasRole('admin')): ?>
        <a href="usuarios.php">Usuarios</a>
        <a href="juegos.php">Juegos</a>
        <?php endif; ?>
        <a href="../index.html" target="_blank">Ver sitio</a>
    </nav>

    <main class="admin-main">

        <?php if ($errorMsg):   echo '<div class="alert alert-error">'   . htmlspecialchars($errorMsg)   . '</div>'; endif; ?>
        <?php if ($successMsg): echo '<div class="alert alert-success">' . htmlspecialchars($successMsg) . '</div>'; endif; ?>

        <h2 style="font-family:'Creepster',cursive;color:var(--blood-red);font-size:2rem;margin-bottom:1.5rem;letter-spacing:.15rem;">
            ✏️ Nuevo Artículo
        </h2>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="steam_image_url" id="nart-steam-url">

            <div class="admin-panel">
                <h3>Información General</h3>
                <div class="form-group">
                    <label for="titulo">Título *</label>
                    <input type="text" id="titulo" name="titulo" required
                           placeholder="Escribe el título del artículo"
                           value="<?= htmlspecialchars($_POST['titulo'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="categoria_id">Categoría *</label>
                        <select id="categoria_id" name="categoria_id" required>
                            <option value="">Selecciona una categoría</option>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>"
                                <?= (($_POST['categoria_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="juego_id">Juego relacionado</label>
                        <select id="juego_id" name="juego_id">
                            <option value="">Sin juego específico</option>
                            <?php foreach ($juegos as $juego): ?>
                            <option value="<?= $juego['id'] ?>"
                                <?= (($_POST['juego_id'] ?? '') == $juego['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($juego['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="extracto">Extracto (resumen corto)</label>
                    <textarea name="extracto" id="extracto" rows="3"
                              placeholder="Resumen breve..."><?= htmlspecialchars($_POST['extracto'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Imagen destacada con Steam + local -->
            <div class="admin-panel">
                <h3>Imagen Destacada</h3>

                <div class="img-source-tabs">
                    <div class="img-source-tab active" onclick="switchNartTab('steam',this)">🎮 Buscar en Steam</div>
                    <div class="img-source-tab" onclick="switchNartTab('local',this)">📁 Subir archivo local</div>
                </div>

                <!-- Panel Steam -->
                <div class="img-panel active" id="nart-panel-steam">
                    <div class="steam-search-box">
                        <h4>🔵 Buscador de Steam</h4>
                        <div class="steam-input-row">
                            <input type="text" id="nart-steam-query"
                                   placeholder="Nombre del juego..."
                                   onkeydown="if(event.key==='Enter'){event.preventDefault();buscarNartSteam();}">
                            <button type="button" class="btn-steam" id="nart-btn-steam" onclick="buscarNartSteam()">🔍 Buscar</button>
                        </div>
                        <div id="nart-steam-status"></div>
                        <div id="nart-steam-results"></div>
                        <div id="nart-steam-preview">
                            <img id="nart-preview-img" src="" alt="">
                            <p id="nart-preview-nombre"></p>
                            <button type="button" onclick="limpiarNartSeleccion()">✕ Quitar</button>
                        </div>
                        <p class="steam-note" id="nart-steam-note" style="display:none;">
                            ✅ La imagen se descargará y guardará en el servidor al crear el artículo.
                        </p>
                    </div>
                </div>

                <!-- Panel local -->
                <div class="img-panel" id="nart-panel-local">
                    <div class="upload-zone" id="upload-zone" onclick="document.getElementById('imagen').click()">
                        <div class="upload-icon">🖼️</div>
                        <p>Haz clic para seleccionar una imagen</p>
                        <p>JPG, PNG, GIF, WEBP — Máx. 5 MB</p>
                    </div>
                    <input type="file" id="imagen" name="imagen"
                           accept=".jpg,.jpeg,.png,.gif,.webp"
                           style="display:none" onchange="previewNartLocal(this)">
                    <img id="img-preview" class="img-preview" alt="Vista previa">
                    <p id="img-name" style="color:#999;font-size:.85rem;margin-top:.3rem;"></p>
                </div>
            </div>

            <div class="admin-panel">
                <h3>Contenido del Artículo</h3>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.8rem;">
                    <button type="button" class="btn btn-sm btn-secondary" onclick="wrapTag('h2')"><b>H2</b></button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="wrapTag('p')">¶ Párrafo</button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="wrapTag('strong')"><b>B</b></button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="wrapTag('em')"><em>I</em></button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="wrapTag('ul')">Lista</button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="insertBlockquote()">❝ Cita</button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="insertRatingBox()">⭐ Rating</button>
                </div>
                <textarea class="content-editor" id="contenido" name="contenido"
                          placeholder="Escribe el contenido HTML aquí..."><?= htmlspecialchars($_POST['contenido'] ?? '') ?></textarea>
                <div style="margin-top:1rem;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="togglePreview()">👁️ Vista previa</button>
                </div>
                <div id="content-preview" style="display:none;margin-top:1rem;padding:1.5rem;background:rgba(10,10,10,.5);border:1px solid var(--fog-gray);color:#ccc;line-height:1.9;font-family:'Crimson Text',serif;font-size:1.1rem;"></div>
            </div>

            <div class="admin-panel">
                <h3>Opciones</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="calificacion">Calificación (0–10)</label>
                        <input type="number" id="calificacion" name="calificacion" min="0" max="10" step="0.1"
                               value="<?= htmlspecialchars($_POST['calificacion'] ?? '0') ?>">
                    </div>
                    <div class="form-group">
                        <label for="tags">Tags (separados por comas)</label>
                        <input type="text" id="tags" name="tags"
                               placeholder="horror, survival, ps5, review..."
                               value="<?= htmlspecialchars($_POST['tags'] ?? '') ?>">
                    </div>
                </div>
                <div style="display:flex;gap:2rem;align-items:center;flex-wrap:wrap;margin-top:.5rem;">
                    <label style="display:flex;align-items:center;gap:.6rem;cursor:pointer;color:var(--ghost-white);">
                        <input type="checkbox" name="publicado" value="1" <?= isset($_POST['publicado']) ? 'checked' : '' ?>>
                        Publicar inmediatamente
                    </label>
                    <label style="display:flex;align-items:center;gap:.6rem;cursor:pointer;color:var(--ghost-white);">
                        <input type="checkbox" name="destacado" value="1" <?= isset($_POST['destacado']) ? 'checked' : '' ?>>
                        Marcar como destacado
                    </label>
                </div>
            </div>

            <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:3rem;">
                <button type="submit" class="btn btn-primary" style="padding:1rem 2.5rem;font-size:1rem;">💾 Guardar artículo</button>
                <a href="articulos.php" class="btn btn-secondary" style="padding:1rem 2rem;">Cancelar</a>
            </div>
        </form>
    </main>
</div>

<script>
// ── Tabs imagen ───────────────────────────────────────────────────────────────
function switchNartTab(tab, el) {
    document.querySelectorAll('.img-source-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.img-panel').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('nart-panel-' + tab).classList.add('active');
    if (tab === 'local') {
        document.getElementById('nart-steam-url').value = '';
        limpiarNartSeleccion(false);
    } else {
        document.getElementById('imagen').value = '';
        document.getElementById('img-preview').style.display = 'none';
    }
}

// ── Preview local ─────────────────────────────────────────────────────────────
function previewNartLocal(input) {
    const preview = document.getElementById('img-preview');
    const nameEl  = document.getElementById('img-name');
    const zone    = document.getElementById('upload-zone');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
        reader.readAsDataURL(file);
        nameEl.textContent = `📎 ${file.name} (${(file.size/1024/1024).toFixed(2)} MB)`;
        zone.style.borderColor = 'var(--pale-green)';
        document.getElementById('nart-steam-url').value = '';
        limpiarNartSeleccion(false);
    }
}

// Drag & drop
const zone = document.getElementById('upload-zone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.borderColor='var(--accent-crimson)'; });
zone.addEventListener('dragleave', () => zone.style.borderColor = '');
zone.addEventListener('drop', e => {
    e.preventDefault(); zone.style.borderColor = '';
    if (e.dataTransfer.files.length) {
        document.getElementById('imagen').files = e.dataTransfer.files;
        previewNartLocal(document.getElementById('imagen'));
    }
});

// ── Búsqueda Steam ────────────────────────────────────────────────────────────
async function buscarNartSteam() {
    const query   = document.getElementById('nart-steam-query').value.trim();
    if (!query) return;
    const btn     = document.getElementById('nart-btn-steam');
    const status  = document.getElementById('nart-steam-status');
    const results = document.getElementById('nart-steam-results');

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
                        onclick="seleccionarNartSteam('${escJS(img)}','${escJS(item.name)}')"
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

function seleccionarNartSteam(imgUrl, nombre) {
    document.querySelectorAll('#nart-steam-results .steam-result-item').forEach(el => {
        el.classList.toggle('selected', el.dataset.url === imgUrl);
    });
    document.getElementById('nart-steam-url').value = imgUrl;
    // Preview usa el proxy solo para visualizar; al guardar el PHP descarga la imagen real
    document.getElementById('nart-preview-img').src = `../php/image_proxy.php?url=${encodeURIComponent(imgUrl)}`;
    document.getElementById('nart-preview-nombre').textContent = nombre;
    document.getElementById('nart-steam-preview').style.display = 'flex';
    document.getElementById('nart-steam-note').style.display = 'block';
    document.getElementById('imagen').value = '';
    document.getElementById('img-preview').style.display = 'none';
}

function limpiarNartSeleccion(resetField = true) {
    if (resetField) document.getElementById('nart-steam-url').value = '';
    document.querySelectorAll('#nart-steam-results .steam-result-item').forEach(el => el.classList.remove('selected'));
    document.getElementById('nart-steam-preview').style.display = 'none';
    document.getElementById('nart-steam-note').style.display = 'none';
}

// ── Editor HTML ───────────────────────────────────────────────────────────────
const editor = document.getElementById('contenido');
function wrapTag(tag) {
    const start = editor.selectionStart, end = editor.selectionEnd;
    const sel = editor.value.substring(start,end) || 'Texto aquí';
    editor.value = editor.value.substring(0,start) + `<${tag}>${sel}</${tag}>` + editor.value.substring(end);
    editor.focus();
}
function insertBlockquote() {
    const pos = editor.selectionStart;
    editor.value = editor.value.substring(0,pos) + '\n<blockquote>\n    <p>Cita importante aquí</p>\n</blockquote>\n' + editor.value.substring(pos);
    editor.focus();
}
function insertRatingBox() {
    const pos = editor.selectionStart;
    editor.value = editor.value.substring(0,pos) + '\n<div class="rating-box">\n    <div class="rating-score">9.5/10</div>\n    <div class="rating-label">Terrorífico</div>\n</div>\n' + editor.value.substring(pos);
    editor.focus();
}
function togglePreview() {
    const prev = document.getElementById('content-preview');
    prev.style.display = prev.style.display==='none' ? 'block' : 'none';
    if (prev.style.display==='block') prev.innerHTML = editor.value || '<p style="color:#666">Sin contenido.</p>';
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function escHTML(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function escJS(s)   { return String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'\\"'); }
</script>
</body>
</html>