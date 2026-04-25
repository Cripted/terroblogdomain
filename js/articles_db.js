/**
 * articles_db.js — Terror Digital
 * Consume las APIs PHP y maneja imágenes correctamente.
 * Detecta automáticamente si está en /pages/ o en la raíz.
 * Soporta imágenes locales (uploads/) y URLs externas (Steam, etc.)
 */

// ── Rutas relativas según la ubicación del HTML ──────────────────────────────
const IS_IN_PAGES = window.location.pathname.includes('/pages/');
const API_BASE    = IS_IN_PAGES ? '../php/' : 'php/';
const UPLOAD_BASE = IS_IN_PAGES ? '../uploads/' : 'uploads/';
const PAGES_BASE  = IS_IN_PAGES ? ''          : 'pages/';

// ── Dominios de Steam que requieren pasar por el proxy ────────────────────────
const STEAM_DOMAINS = [
    'cdn.akamai.steamstatic.com',
    'cdn.cloudflare.steamstatic.com',
    'steamcdn-a.akamaihd.net',
    'store.steampowered.com',
];

function isSteamUrl(url) {
    if (!url) return false;
    try {
        const host = new URL(url).hostname;
        return STEAM_DOMAINS.some(d => host === d || host.endsWith('.' + d));
    } catch { return false; }
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function getArticleId() {
    const params = new URLSearchParams(window.location.search);
    return params.get('id');
}

function formatearFecha(fecha) {
    const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                   'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    const d = new Date(fecha);
    return `${d.getDate()} de ${meses[d.getMonth()]}, ${d.getFullYear()}`;
}

/**
 * Resuelve la URL correcta de una imagen.
 * - URLs de Steam  → pasan por el proxy PHP para evitar bloqueo de hotlinking.
 * - Otras externas → se usan directamente.
 * - Archivos locales → se les añade el prefijo uploads/.
 */
function resolveImageUrl(imagen) {
    if (!imagen) return null;

    if (isSteamUrl(imagen)) {
        // Pasar por el proxy para evitar que Steam bloquee la petición
        return `${API_BASE}image_proxy.php?url=${encodeURIComponent(imagen)}`;
    }

    if (imagen.startsWith('http://') || imagen.startsWith('https://')) {
        return imagen; // Otra URL externa: usar directamente
    }

    return UPLOAD_BASE + imagen; // Archivo local
}

/**
 * Devuelve el HTML de thumbnail para una tarjeta.
 * Si hay imagen real usa <img>, si no usa emoji placeholder.
 */
function thumbnailHTML(art) {
    if (art.imagen_destacada) {
        const src = resolveImageUrl(art.imagen_destacada);
        return `<img class="thumb-real"
                     src="${src}"
                     alt="${art.titulo}"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                <span class="thumb-emoji" style="display:none">${art.categoria_icono || '📰'}</span>`;
    }
    return `<span class="thumb-emoji">${art.categoria_icono || '📰'}</span>`;
}

function articleCardHTML(art) {
    return `
    <article class="article-card">
        <div class="article-thumbnail">${thumbnailHTML(art)}</div>
        <div class="article-body">
            <span class="category-tag">${art.categoria || 'Artículo'}</span>
            <h3>${art.titulo}</h3>
            <p class="meta">${formatearFecha(art.fecha_publicacion)}</p>
            <p class="excerpt">${art.extracto || ''}</p>
            <a href="${PAGES_BASE}articulo.html?id=${art.slug}" class="read-more">Leer más →</a>
        </div>
    </article>`;
}

// ── Cargar artículo individual ────────────────────────────────────────────────

async function loadArticle() {
    const slug = getArticleId();
    if (!slug) {
        document.getElementById('article-heading').textContent = 'Artículo no encontrado';
        document.getElementById('article-body').innerHTML = '<p>Lo sentimos, el artículo que buscas no existe.</p>';
        return;
    }

    try {
        const res = await fetch(`${API_BASE}api_articulos.php?action=get&slug=${encodeURIComponent(slug)}`);
        const art = await res.json();

        if (art.error) {
            document.getElementById('article-heading').textContent = 'Artículo no encontrado';
            document.getElementById('article-body').innerHTML = `<p>${art.error}</p>`;
            return;
        }

        document.title = `${art.titulo} — TERROR DIGITAL`;

        const elTitle    = document.getElementById('article-title');
        const elCategory = document.getElementById('article-category');
        const elHeading  = document.getElementById('article-heading');
        const elAuthor   = document.getElementById('article-author');
        const elDate     = document.getElementById('article-date');
        const elViews    = document.getElementById('views-count');
        const elImage    = document.getElementById('article-image');
        const elBody     = document.getElementById('article-body');
        const elTags     = document.getElementById('article-tags');

        if (elTitle)    elTitle.textContent    = art.titulo;
        if (elCategory) elCategory.textContent = art.categoria || 'Artículo';
        if (elHeading)  elHeading.textContent  = art.titulo;
        if (elAuthor)   elAuthor.textContent   = `Por ${art.autor}`;
        if (elDate)     elDate.textContent     = formatearFecha(art.fecha_publicacion);
        if (elViews)    elViews.textContent    = (art.vistas || 0).toLocaleString();

        // Imagen destacada — soporta Steam (proxy), otras URLs externas y locales
        if (elImage) {
            if (art.imagen_destacada) {
                const imgSrc = resolveImageUrl(art.imagen_destacada);
                elImage.innerHTML = `
                    <img src="${imgSrc}"
                         alt="${art.titulo}"
                         onerror="this.outerHTML='<div class=\\'image-placeholder\\'>${art.categoria_icono || '📰'}</div>'">`;
            } else {
                elImage.innerHTML = `<div class="image-placeholder">${art.categoria_icono || '📰'}</div>`;
            }
        }

        if (elBody) elBody.innerHTML = art.contenido || '';

        // Tags
        if (elTags) {
            const tags = art.tags ? (typeof art.tags === 'string' ? art.tags.split(',') : art.tags) : [];
            elTags.innerHTML = tags.map(t => `<span class="tag">${t.trim()}</span>`).join('');
        }

        loadRelatedArticles(art.id);

    } catch (err) {
        console.error('Error al cargar artículo:', err);
        const el = document.getElementById('article-body');
        if (el) el.innerHTML = '<p>Error al cargar el artículo. Por favor intenta más tarde.</p>';
    }
}

// ── Artículos relacionados ────────────────────────────────────────────────────

async function loadRelatedArticles(articleId) {
    try {
        const res  = await fetch(`${API_BASE}api_articulos.php?action=relacionados&articulo_id=${articleId}`);
        const arts = await res.json();
        if (!arts || !arts.length) return;

        const container = document.querySelector('.related-articles .articles-grid');
        if (container) container.innerHTML = arts.map(articleCardHTML).join('');
    } catch (err) {
        console.error('Error artículos relacionados:', err);
    }
}

// ── Compartir ─────────────────────────────────────────────────────────────────

function shareArticle(platform) {
    const url   = window.location.href;
    const title = (document.getElementById('article-heading') || {}).textContent || 'Terror Digital';
    const enc   = encodeURIComponent;

    const urls = {
        twitter:  `https://twitter.com/intent/tweet?text=${enc(title)}&url=${enc(url)}`,
        facebook: `https://www.facebook.com/sharer/sharer.php?u=${enc(url)}`,
        reddit:   `https://reddit.com/submit?url=${enc(url)}&title=${enc(title)}`,
    };

    if (platform === 'copy') {
        navigator.clipboard.writeText(url).then(() => alert('¡Link copiado al portapapeles!'));
        return;
    }
    if (urls[platform]) window.open(urls[platform], '_blank');
}

// ── Listado de artículos ──────────────────────────────────────────────────────

async function loadArticlesList(categoria = null, limit = 9, containerId = 'articles-container') {
    let url = `${API_BASE}api_articulos.php?action=list&limit=${limit}`;
    if (categoria) url += `&categoria=${encodeURIComponent(categoria)}`;

    try {
        const res  = await fetch(url);
        const arts = await res.json();
        if (!Array.isArray(arts) || !arts.length) return;

        const container = document.getElementById(containerId)
                       || document.querySelector('.articles-grid');
        if (container) container.innerHTML = arts.map(articleCardHTML).join('');
    } catch (err) {
        console.error('Error al cargar listado:', err);
    }
}

// ── Artículo destacado ────────────────────────────────────────────────────────

async function loadFeaturedArticle() {
    try {
        const res  = await fetch(`${API_BASE}api_articulos.php?action=destacados&limit=1`);
        const arts = await res.json();
        if (!arts || !arts.length) return;

        const art     = arts[0];
        const content = document.getElementById('featured-content');
        const imgBox  = document.querySelector('#featured-main .featured-image');

        if (content) {
            content.innerHTML = `
                <span class="category-tag">Destacado</span>
                <h2>${art.titulo}</h2>
                <p class="meta">Por ${art.autor} | ${formatearFecha(art.fecha_publicacion)} | ${(art.vistas||0).toLocaleString()} vistas</p>
                <p class="excerpt">${art.extracto || ''}</p>
                <a href="${PAGES_BASE}articulo.html?id=${art.slug}" class="read-more">Leer análisis completo →</a>
            `;
        }

        if (imgBox && art.imagen_destacada) {
            const imgSrc = resolveImageUrl(art.imagen_destacada);
            imgBox.innerHTML = `
                <img class="thumb-real"
                     src="${imgSrc}"
                     alt="${art.titulo}"
                     onerror="this.style.display='none'">`;
        }
    } catch (err) {
        console.error('Error artículo destacado:', err);
    }
}

// ── Auto-inicialización ───────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('article-body')) {
        loadArticle();
        return;
    }

    const path = window.location.pathname;
    if (path.endsWith('/') || path.endsWith('index.html')) {
        loadFeaturedArticle();
        loadArticlesList(null, 6, 'articles-container');
    }
});