/**
 * script.js — Terror Digital
 * Utilidades globales del sitio público.
 */

document.addEventListener('DOMContentLoaded', function () {

    // Smooth scroll para anclas internas
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const targetId = this.getAttribute('href').substring(1);
            const target   = document.getElementById(targetId);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // Hover suave en tarjetas (complementa el CSS, por si el CSS no carga)
    document.querySelectorAll('.article-card, .news-item').forEach(card => {
        card.addEventListener('mouseenter', () => card.style.transform = 'translateY(-5px)');
        card.addEventListener('mouseleave', () => card.style.transform = 'translateY(0)');
    });
});

// Parpadeo aleatorio del logo
setInterval(() => {
    const logo = document.querySelector('.logo');
    if (logo && Math.random() > 0.95) {
        logo.style.opacity = '0.5';
        setTimeout(() => (logo.style.opacity = '1'), 100);
    }
}, 3000);

// ── Like ──────────────────────────────────────────────────────────────────────

function toggleLike(threadId) {
    const likeKey  = `liked_${threadId}`;
    const countKey = `likes_${threadId}`;
    const hasLiked = localStorage.getItem(likeKey) === 'true';
    let count      = parseInt(localStorage.getItem(countKey)) || 0;

    if (hasLiked) {
        count = Math.max(0, count - 1);
        localStorage.setItem(likeKey, 'false');
    } else {
        count++;
        localStorage.setItem(likeKey, 'true');
    }
    localStorage.setItem(countKey, count);
    return { liked: !hasLiked, count };
}

function getLikeStatus(threadId) {
    return {
        liked: localStorage.getItem(`liked_${threadId}`) === 'true',
        count: parseInt(localStorage.getItem(`likes_${threadId}`)) || 0
    };
}

// ── Vistas ────────────────────────────────────────────────────────────────────

function incrementViews(articleId) {
    let v = parseInt(localStorage.getItem(`views_${articleId}`)) || 0;
    v++;
    localStorage.setItem(`views_${articleId}`, v);
    return v;
}

function getViews(articleId) {
    return parseInt(localStorage.getItem(`views_${articleId}`)) || 0;
}