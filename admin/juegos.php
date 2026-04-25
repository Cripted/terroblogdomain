<?php
require_once '../config/auth.php';
$auth->requireRole('admin');
$user = $auth->getCurrentUser();
$db   = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['nuevo_juego'])) {
        $nom  = sanitize($_POST['nombre'] ?? '');
        $slug = generateSlug($nom);
        if ($nom) {
            $dev   = sanitize($_POST['desarrollador']   ?? '');
            $desc  = sanitize($_POST['descripcion']     ?? '');
            $anio  = !empty($_POST['anio_lanzamiento']) ? (int)$_POST['anio_lanzamiento'] : null;
            $cal   = !empty($_POST['calificacion'])     ? (float)$_POST['calificacion']   : null;

            // Imagen: puede venir de Steam (URL) o de subida local
            $imagen_portada = null;

            if (!empty($_POST['steam_image_url'])) {
                // Guardar la URL directa de Steam
                $imagen_portada = sanitize($_POST['steam_image_url']);
            } elseif (!empty($_FILES['imagen_portada']['name']) && $_FILES['imagen_portada']['error'] === UPLOAD_ERR_OK) {
                $up = uploadImage($_FILES['imagen_portada'], 'juego');
                if ($up['success']) {
                    $imagen_portada = $up['url']; // URL local
                }
            }

            $db->prepare("INSERT INTO juegos (nombre, slug, descripcion, desarrollador, anio_lanzamiento, calificacion, imagen_portada) VALUES (?, ?, ?, ?, ?, ?, ?)")
               ->execute([$nom, $slug, $desc, $dev, $anio, $cal, $imagen_portada]);
            setFlashMessage('success', "Juego \"$nom\" creado");
        } else {
            setFlashMessage('error', 'Nombre requerido');
        }
    } elseif (isset($_POST['toggle_juego'])) {
        $db->prepare("UPDATE juegos SET activo = NOT activo WHERE id = ?")->execute([(int)$_POST['toggle_juego']]);
        setFlashMessage('success', 'Estado actualizado');
    }
    redirect(SITE_URL . '/admin/juegos.php');
}

$juegos = $db->query("
    SELECT j.*,
           (SELECT COUNT(*) FROM discusiones d WHERE d.juego_id = j.id) AS total_disc,
           (SELECT COUNT(*) FROM articulos a   WHERE a.juego_id = j.id) AS total_arts
    FROM juegos j
    ORDER BY j.nombre
")->fetchAll();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Juegos - Admin | Terror Digital</title>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Text:wght@400;600;700&family=Rubik:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        /* ── Steam search panel ── */
        .steam-search-box {
            background: rgba(10,10,10,.8);
            border: 1px solid #1b2838; /* color Steam */
            padding: 1.2rem;
            margin-top: .8rem;
        }
        .steam-search-box h4 {
            color: #66c0f4; /* azul Steam */
            font-family: 'Rubik', sans-serif;
            font-size: .85rem;
            text-transform: uppercase;
            letter-spacing: .06rem;
            margin-bottom: .8rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .steam-input-row {
            display: flex;
            gap: .6rem;
        }
        .steam-input-row input {
            flex: 1;
            padding: .6rem .9rem;
            background: #1b2838;
            border: 1px solid #2a475e;
            color: #c6d4df;
            font-family: 'Rubik', sans-serif;
            font-size: .88rem;
        }
        .steam-input-row input:focus { outline: none; border-color: #66c0f4; }
        .btn-steam {
            background: #1b2838;
            border: 1px solid #66c0f4;
            color: #66c0f4;
            padding: .6rem 1.1rem;
            font-family: 'Rubik', sans-serif;
            font-size: .82rem;
            cursor: pointer;
            white-space: nowrap;
            transition: all .2s;
        }
        .btn-steam:hover { background: #2a475e; }
        .btn-steam:disabled { opacity: .5; cursor: not-allowed; }

        #steam-results {
            display: none;
            margin-top: .9rem;
            max-height: 340px;
            overflow-y: auto;
            border: 1px solid #2a475e;
        }
        .steam-result-item {
            display: flex;
            align-items: center;
            gap: .8rem;
            padding: .6rem .8rem;
            cursor: pointer;
            border-bottom: 1px solid #1b2838;
            transition: background .15s;
        }
        .steam-result-item:last-child { border-bottom: none; }
        .steam-result-item:hover { background: #2a475e; }
        .steam-result-item.selected { background: rgba(102,192,244,.12); border-left: 3px solid #66c0f4; }
        .steam-result-item img {
            width: 80px;
            height: 37px;
            object-fit: cover;
            flex-shrink: 0;
            background: #0a0a0a;
        }
        .steam-result-info { flex: 1; min-width: 0; }
        .steam-result-info strong {
            display: block;
            color: #c6d4df;
            font-family: 'Rubik', sans-serif;
            font-size: .85rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .steam-result-info small {
            color: #66c0f4;
            font-size: .72rem;
            font-family: 'Rubik', sans-serif;
        }
        .steam-check { color: #66c0f4; font-size: 1.1rem; flex-shrink: 0; opacity: 0; }
        .steam-result-item.selected .steam-check { opacity: 1; }

        #steam-selected-preview {
            display: none;
            margin-top: .8rem;
            padding: .8rem;
            background: rgba(102,192,244,.07);
            border: 1px solid #66c0f4;
            display: none;
            align-items: center;
            gap: 1rem;
        }
        #steam-selected-preview img {
            width: 120px;
            height: 56px;
            object-fit: cover;
            border: 1px solid #2a475e;
        }
        #steam-selected-preview p {
            color: #c6d4df;
            font-family: 'Rubik', sans-serif;
            font-size: .85rem;
        }
        #steam-selected-preview button {
            background: transparent;
            border: 1px solid #c44;
            color: #c44;
            padding: .3rem .7rem;
            font-size: .78rem;
            cursor: pointer;
            margin-left: auto;
        }
        #steam-selected-preview button:hover { background: rgba(200,50,50,.2); }

        .img-source-tabs {
            display: flex;
            gap: 0;
            margin-bottom: .8rem;
        }
        .img-source-tab {
            flex: 1;
            padding: .55rem;
            text-align: center;
            background: #0a0a0a;
            border: 1px solid #222;
            color: #666;
            font-family: 'Rubik', sans-serif;
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .05rem;
            cursor: pointer;
            transition: all .2s;
        }
        .img-source-tab.active {
            background: rgba(139,0,0,.2);
            border-color: var(--blood-red);
            color: var(--ghost-white);
        }

        .img-panel { display: none; }
        .img-panel.active { display: block; }

        #steam-status {
            font-family: 'Rubik', sans-serif;
            font-size: .78rem;
            color: #888;
            margin-top: .5rem;
            min-height: 1.2em;
        }
        #steam-status.error { color: #f66; }
        #steam-status.ok { color: #6f6; }

        .juego-portada-cell img {
            width: 80px;
            height: 37px;
            object-fit: cover;
            border: 1px solid #222;
        }
    </style>
</head>
<body>
<div class="grain"></div>
<div class="admin-layout">
    <?php include 'partials/sidebar.php'; ?>
    <main class="admin-main">
        <div class="admin-header">
            <h1>🎮 Juegos</h1>
        </div>

        <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>

        <!-- ── Formulario nuevo juego ── -->
        <div class="admin-section">
            <h2>Agregar juego</h2>
            <form method="POST" enctype="multipart/form-data" class="admin-form" id="form-nuevo-juego">
                <input type="hidden" name="nuevo_juego" value="1">
                <!-- Campo oculto que recibe la URL de Steam seleccionada -->
                <input type="hidden" name="steam_image_url" id="steam_image_url">

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;">
                    <div class="form-group">
                        <label>Nombre *</label>
                        <input type="text" name="nombre" id="campo-nombre" required placeholder="Silent Hill 2"
                               oninput="onNombreInput(this.value)">
                    </div>
                    <div class="form-group">
                        <label>Desarrollador</label>
                        <input type="text" name="desarrollador" placeholder="Bloober Team">
                    </div>
                    <div class="form-group">
                        <label>Año</label>
                        <input type="number" name="anio_lanzamiento" min="1990" max="2030" placeholder="2024">
                    </div>
                    <div class="form-group">
                        <label>Calificación (0-10)</label>
                        <input type="number" name="calificacion" min="0" max="10" step="0.1" placeholder="9.5">
                    </div>
                </div>

                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="descripcion" rows="2" placeholder="Breve descripción del juego..."></textarea>
                </div>

                <!-- ── Imagen: tabs Steam / Local ── -->
                <div class="form-group">
                    <label>Imagen de portada</label>

                    <div class="img-source-tabs">
                        <div class="img-source-tab active" onclick="switchImgTab('steam', this)">
                            🎮 Buscar en Steam
                        </div>
                        <div class="img-source-tab" onclick="switchImgTab('local', this)">
                            📁 Subir imagen local
                        </div>
                    </div>

                    <!-- Panel Steam -->
                    <div class="img-panel active" id="panel-steam">
                        <div class="steam-search-box">
                            <h4>🔵 Buscador de Steam</h4>
                            <div class="steam-input-row">
                                <input type="text" id="steam-query"
                                       placeholder="Escribe el nombre del juego..."
                                       onkeydown="if(event.key==='Enter'){event.preventDefault();buscarSteam();}">
                                <button type="button" class="btn-steam" id="btn-steam-buscar" onclick="buscarSteam()">
                                    🔍 Buscar
                                </button>
                            </div>
                            <div id="steam-status"></div>

                            <!-- Resultados -->
                            <div id="steam-results"></div>

                            <!-- Preview seleccionado -->
                            <div id="steam-selected-preview">
                                <img id="steam-preview-img" src="" alt="">
                                <p id="steam-preview-nombre"></p>
                                <button type="button" onclick="limpiarSeleccion()">✕ Quitar</button>
                            </div>
                        </div>
                    </div>

                    <!-- Panel Local -->
                    <div class="img-panel" id="panel-local">
                        <input type="file" name="imagen_portada" accept=".jpg,.jpeg,.png,.gif,.webp"
                               id="input-local-img" onchange="previewLocal(this)">
                        <small class="hint-txt">JPG, PNG, GIF, WebP. Máx 5 MB.</small>
                        <img id="local-preview" src="" alt=""
                             style="display:none;max-width:200px;max-height:100px;object-fit:cover;margin-top:.5rem;border:1px solid #333;">
                    </div>
                </div>

                <button type="submit" class="btn-sm">➕ Crear juego</button>
            </form>
        </div>

        <!-- ── Tabla de juegos ── -->
        <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Portada</th>
                    <th>Nombre</th>
                    <th>Slug</th>
                    <th>Desarrollador</th>
                    <th>Año</th>
                    <th>Cal.</th>
                    <th>Arts.</th>
                    <th>Disc.</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($juegos as $j): ?>

<td class="juego-portada-cell">
    <?php if (!empty($j['imagen_portada'])): ?>
        <?php
        // Si ya es una URL completa (Steam, http/https) úsala directamente.
        // Si es un nombre de archivo local, añade el prefijo uploads/.
        $isExternal = str_starts_with($j['imagen_portada'], 'http://') 
                   || str_starts_with($j['imagen_portada'], 'https://');
        $imgSrc = $isExternal
            ? htmlspecialchars($j['imagen_portada'])
            : '../uploads/' . htmlspecialchars($j['imagen_portada']);
        ?>
        <img src="<?= $imgSrc ?>"
             alt="<?= htmlspecialchars($j['nombre']) ?>"
             onerror="this.style.display='none'">
    <?php else: ?>
        <span style="color:#444;font-size:.75rem;">Sin imagen</span>
    <?php endif; ?>
</td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($juegos)): ?>
            <tr><td colspan="10" style="text-align:center;color:#666;padding:2rem;">No hay juegos registrados todavía.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </main>
</div>

<script>
// ── Tabs imagen ───────────────────────────────────────────────────────────────
function switchImgTab(tab, el) {
    document.querySelectorAll('.img-source-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.img-panel').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('panel-' + tab).classList.add('active');

    if (tab === 'local') {
        document.getElementById('steam_image_url').value = '';
        limpiarSeleccion(false);
    } else {
        document.getElementById('input-local-img').value = '';
        document.getElementById('local-preview').style.display = 'none';
    }
}

// ── Auto-fill del buscador Steam cuando escribe el nombre ────────────────────
let nombreTimer = null;
function onNombreInput(val) {
    clearTimeout(nombreTimer);
    if (val.trim().length >= 2) {
        nombreTimer = setTimeout(() => {
            document.getElementById('steam-query').value = val.trim();
        }, 600);
    }
}

// ── Preview imagen local ──────────────────────────────────────────────────────
function previewLocal(input) {
    const prev = document.getElementById('local-preview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { prev.src = e.target.result; prev.style.display = 'block'; };
        reader.readAsDataURL(input.files[0]);
        document.getElementById('steam_image_url').value = '';
        limpiarSeleccion(false);
    }
}

// ── Búsqueda Steam (a través del proxy PHP para evitar CORS) ─────────────────
let steamAppSeleccionado = null;

async function buscarSteam() {
    const query = document.getElementById('steam-query').value.trim();
    if (!query) return;

    const btn    = document.getElementById('btn-steam-buscar');
    const status = document.getElementById('steam-status');
    const results= document.getElementById('steam-results');

    btn.disabled = true;
    btn.textContent = 'Buscando...';
    status.className = '';
    status.textContent = 'Consultando Steam...';
    results.style.display = 'none';
    results.innerHTML = '';

    try {
        // Usamos el proxy PHP para evitar el bloqueo CORS del navegador
        const url = `../php/steam_proxy.php?q=${encodeURIComponent(query)}`;
        const res  = await fetch(url);

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const data = await res.json();
        const items = data?.items ?? [];

        if (!items.length) {
            status.className = 'error';
            status.textContent = 'No se encontraron juegos con ese nombre en Steam.';
            btn.disabled = false;
            btn.textContent = '🔍 Buscar';
            return;
        }

        status.className = 'ok';
        status.textContent = `${items.length} resultado(s). Haz clic en uno para seleccionarlo.`;

        results.innerHTML = items.slice(0, 15).map(item => {
            const imgUrl = `https://cdn.akamai.steamstatic.com/steam/apps/${item.id}/header.jpg`;
            const imgPreview = `../php/image_proxy.php?url=${encodeURIComponent(imgUrl)}`;
            return `
            <div class="steam-result-item" onclick="seleccionarJuegoSteam(${item.id}, '${escJS(item.name)}', '${escJS(imgUrl)}')" data-appid="${item.id}">
                <img src="${imgPreview}" alt="${escHTML(item.name)}"
                     onerror="this.src='https://placehold.co/80x37/1b2838/66c0f4?text=?'">
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
        status.textContent = 'Error al conectar con Steam. ¿Existe php/steam_proxy.php?';
        console.error(err);
    }

    btn.disabled = false;
    btn.textContent = '🔍 Buscar';
}

function seleccionarJuegoSteam(appId, nombre, imgUrl) {
    steamAppSeleccionado = { appId, nombre, imgUrl };

    // Marcar visualmente en la lista
    document.querySelectorAll('.steam-result-item').forEach(el => {
        el.classList.toggle('selected', el.dataset.appid == appId);
    });

    // Guardar la URL en el campo oculto — se enviará al formulario PHP
    document.getElementById('steam_image_url').value = imgUrl;

    // Mostrar preview — fix: usar flex explícito
    const preview  = document.getElementById('steam-selected-preview');
    const previewImg = document.getElementById('steam-preview-img');
    const previewNom = document.getElementById('steam-preview-nombre');

    previewImg.src         = imgUrl;
    previewImg.alt         = nombre;
    previewNom.textContent = nombre;
    preview.style.display  = 'flex';   // ← fix: era display:none en el CSS inline

    // Limpiar input local
    document.getElementById('input-local-img').value = '';
    document.getElementById('local-preview').style.display = 'none';
}

function limpiarSeleccion(resetField = true) {
    steamAppSeleccionado = null;
    if (resetField) document.getElementById('steam_image_url').value = '';
    document.querySelectorAll('.steam-result-item').forEach(el => el.classList.remove('selected'));
    document.getElementById('steam-selected-preview').style.display = 'none';
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function escHTML(s) {
    return String(s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escJS(s) {
    return String(s)
        .replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'\\"');
}
</script>
</body>
</html>