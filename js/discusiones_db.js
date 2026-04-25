/**
 * discusiones_db.js — Terror Digital
 * Sistema de discusiones conectado con la API PHP.
 */

const IS_IN_PAGES_D = window.location.pathname.includes('/pages/');
const API_BASE_D    = IS_IN_PAGES_D ? '../php/' : 'php/';

let currentThreadId = null;

// ── Helpers ───────────────────────────────────────────────────────────────────

function formatearFechaD(fecha) {
    const date = new Date(fecha);
    const ahora = new Date();
    const diff = Math.floor((ahora - date) / 1000);

    if (diff < 60)     return 'hace unos segundos';
    if (diff < 3600)   return `hace ${Math.floor(diff / 60)} minutos`;
    if (diff < 86400)  return `hace ${Math.floor(diff / 3600)} horas`;
    if (diff < 604800) return `hace ${Math.floor(diff / 86400)} días`;

    const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                   'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    return `${date.getDate()} de ${meses[date.getMonth()]}, ${date.getFullYear()}`;
}

// ── Cargar discusiones ────────────────────────────────────────────────────────

async function loadDiscussions() {
    const gameSelect = document.getElementById('game-select');
    if (!gameSelect) return;

    const container   = document.getElementById('threads-container');
    const selectedGame = gameSelect.value;

    container.innerHTML = '<p style="text-align:center;color:#999;padding:2rem;">Cargando discusiones...</p>';

    try {
        const res     = await fetch(`${API_BASE_D}api_discusiones.php?action=list&juego=${encodeURIComponent(selectedGame)}`);
        const threads = await res.json();

        if (threads.error) {
            container.innerHTML = `<p style="text-align:center;color:#ff6b6b;">${threads.error}</p>`;
            return;
        }

        if (!threads.length) {
            container.innerHTML = '<p style="text-align:center;color:#999;padding:2rem;">No hay discusiones todavía. ¡Sé el primero en iniciar una!</p>';
            return;
        }

        container.innerHTML = '';
        threads.forEach(thread => {
            const views = thread.vistas >= 1000
                ? (thread.vistas / 1000).toFixed(1) + 'K'
                : thread.vistas;

            const el = document.createElement('div');
            el.className = 'thread';
            el.innerHTML = `
                <div class="thread-header">
                    <h4 class="thread-title">${thread.titulo}</h4>
                    <div class="thread-meta">
                        <span>💬 ${thread.total_comentarios || 0} respuestas</span>
                        <span>👁️ ${views} vistas</span>
                    </div>
                </div>
                <p class="thread-content">${thread.contenido}</p>
                <p class="meta">
                    Por <span class="thread-author">${thread.autor_nombre}</span>
                    | ${formatearFechaD(thread.fecha_creacion)}
                </p>
                <div class="thread-actions">
                    <button class="like-btn" onclick="handleLike(${thread.id}, event)">
                        <span class="like-icon">🤍</span>
                        <span class="like-count">${thread.likes || 0}</span> Me gusta
                    </button>
                    <button class="comment-btn" onclick="openCommentsModal(${thread.id})">
                        💬 Ver comentarios (${thread.total_comentarios || 0})
                    </button>
                </div>
            `;
            container.appendChild(el);
        });

    } catch (err) {
        console.error('Error al cargar discusiones:', err);
        container.innerHTML = '<p style="text-align:center;color:#ff6b6b;">Error al cargar discusiones. ¿Está corriendo el servidor PHP?</p>';
    }
}

// ── Like ──────────────────────────────────────────────────────────────────────

async function handleLike(threadId, event) {
    event.stopPropagation();
    const btn = event.currentTarget;

    const likeKey = `liked_d_${threadId}`;
    if (localStorage.getItem(likeKey) === 'true') {
        alert('Ya has dado like a esta discusión');
        return;
    }

    try {
        const fd = new FormData();
        fd.append('action', 'like');
        fd.append('discusion_id', threadId);

        const res    = await fetch(`${API_BASE_D}api_discusiones.php`, { method: 'POST', body: fd });
        const result = await res.json();

        if (result.success) {
            btn.querySelector('.like-icon').textContent  = '❤️';
            btn.querySelector('.like-count').textContent = result.likes;
            btn.classList.add('liked');
            localStorage.setItem(likeKey, 'true');
        }
    } catch (err) {
        console.error('Error al dar like:', err);
    }
}

// ── Modal comentarios ─────────────────────────────────────────────────────────

async function openCommentsModal(threadId) {
    currentThreadId = threadId;

    try {
        const res    = await fetch(`${API_BASE_D}api_discusiones.php?action=get&id=${threadId}`);
        const thread = await res.json();

        if (thread.error) { alert(thread.error); return; }

        document.getElementById('modal-thread-title').textContent = thread.titulo;
        document.getElementById('modal-thread-content').innerHTML = `
            <p class="thread-content">${thread.contenido}</p>
            <p class="meta">Por <span class="thread-author">${thread.autor_nombre}</span>
            | ${formatearFechaD(thread.fecha_creacion)}</p>
        `;

        await loadComments(threadId);
        document.getElementById('comments-modal').style.display = 'block';

    } catch (err) {
        console.error('Error al abrir modal:', err);
    }
}

function closeCommentsModal() {
    document.getElementById('comments-modal').style.display = 'none';
    currentThreadId = null;
    loadDiscussions();
}

async function loadComments(threadId) {
    const list  = document.getElementById('comments-list');
    const count = document.getElementById('comment-count');

    try {
        const res      = await fetch(`${API_BASE_D}api_discusiones.php?action=comentarios&discusion_id=${threadId}`);
        const comments = await res.json();

        if (count) count.textContent = comments.length;

        if (!comments.length) {
            list.innerHTML = '<p class="no-comments">No hay comentarios todavía. ¡Sé el primero en comentar!</p>';
            return;
        }

        list.innerHTML = comments.map(c => `
            <div class="comment">
                <div class="comment-header">
                    <strong class="thread-author">${c.autor_nombre}</strong>
                    <span class="comment-time">${formatearFechaD(c.fecha_creacion)}</span>
                </div>
                <p class="comment-text">${c.contenido}</p>
            </div>
        `).join('');

    } catch (err) {
        console.error('Error al cargar comentarios:', err);
        list.innerHTML = '<p class="no-comments">Error al cargar comentarios.</p>';
    }
}

// ── Agregar comentario ────────────────────────────────────────────────────────

async function addComment(event) {
    event.preventDefault();
    if (!currentThreadId) return;

    const username = document.getElementById('comment-username').value.trim();
    const text     = document.getElementById('comment-text').value.trim();

    if (!username || !text) { alert('Por favor completa todos los campos'); return; }

    try {
        const fd = new FormData();
        fd.append('action', 'comentar');
        fd.append('discusion_id', currentThreadId);
        fd.append('autor_nombre', username);
        fd.append('contenido', text);

        const res    = await fetch(`${API_BASE_D}api_discusiones.php`, { method: 'POST', body: fd });
        const result = await res.json();

        if (result.success) {
            document.getElementById('comment-form').reset();
            await loadComments(currentThreadId);
            document.getElementById('comments-list').scrollIntoView({ behavior: 'smooth' });
        } else {
            alert(result.message || 'Error al publicar comentario');
        }
    } catch (err) {
        console.error('Error al agregar comentario:', err);
        alert('Error al publicar comentario');
    }
}

// ── Nueva discusión ───────────────────────────────────────────────────────────

async function submitDiscussion(event) {
    event.preventDefault();

    const title    = document.getElementById('thread-title').value.trim();
    const username = document.getElementById('username').value.trim();
    const comment  = document.getElementById('comment').value.trim();
    const gameSlug = document.getElementById('game-select').value;

    if (!title || !username || !comment) { alert('Por favor completa todos los campos'); return; }

    try {
        const fd = new FormData();
        fd.append('action',      'nueva');
        fd.append('titulo',      title);
        fd.append('autor_nombre', username);
        fd.append('contenido',   comment);
        fd.append('juego_slug',  gameSlug);

        const res    = await fetch(`${API_BASE_D}api_discusiones.php`, { method: 'POST', body: fd });
        const result = await res.json();

        if (result.success) {
            alert(`¡Gracias, ${username}!\n\nTu tema "${title}" ha sido publicado.`);
            event.target.reset();
            await loadDiscussions();
            document.getElementById('threads-container').scrollIntoView({ behavior: 'smooth' });
        } else {
            alert(result.message || 'Error al publicar discusión');
        }
    } catch (err) {
        console.error('Error al enviar discusión:', err);
        alert('Error al publicar discusión. ¿Está corriendo el servidor PHP?');
    }
}

// ── Cerrar modal al click fuera ───────────────────────────────────────────────

window.addEventListener('click', function (event) {
    const modal = document.getElementById('comments-modal');
    if (event.target === modal) closeCommentsModal();
});

// ── Init ──────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('game-select')) loadDiscussions();
});