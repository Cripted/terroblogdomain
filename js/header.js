/**
 * header.js — Terror Digital
 * Inyecta el header con modal de login/registro integrado.
 * Funciona tanto en index.html (raíz) como en /pages/*.html
 */

(function () {
    const enPages = window.location.pathname.includes('/pages/');
    const raiz    = enPages ? '../' : '';
    const php     = raiz + 'php/';
    const pages   = raiz + 'pages/';
    const admin   = raiz + 'admin/';

    function navActivo(palabra) {
        return window.location.pathname.includes(palabra)
            ? ' style="color:var(--accent-crimson);border-color:var(--blood-red);"'
            : '';
    }

    // ── Renderizar header ─────────────────────────────────────────────────────
    function injectHeader(usuario) {
        const header = document.querySelector('header');
        if (!header) return;

        const btnSesion = usuario
            ? `<div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;justify-content:center;">
                   <span style="color:var(--pale-green);font-family:'Rubik',sans-serif;font-size:.85rem;padding:.4rem .9rem;border:1px solid var(--fog-gray);">👤 ${escHtml(usuario.nombre)}</span>
                   ${usuario.esAdmin ? `<a href="${admin}index.php" style="color:var(--ghost-white);text-decoration:none;font-family:'Rubik',sans-serif;font-size:.85rem;padding:.4rem .9rem;border:1px solid var(--fog-gray);">⚙️ Panel</a>` : ''}
                   <button onclick="TD_logout()" style="background:transparent;border:1px solid #555;color:#aaa;font-family:'Rubik',sans-serif;font-size:.85rem;padding:.4rem .9rem;cursor:pointer;">Salir</button>
               </div>`
            : `<button onclick="TD_abrirModal('login')"
                       style="background:var(--blood-red);border:none;color:var(--ghost-white);font-family:'Rubik',sans-serif;font-size:.9rem;font-weight:700;text-transform:uppercase;letter-spacing:.08rem;padding:.55rem 1.5rem;cursor:pointer;transition:background .2s;"
                       onmouseover="this.style.background='var(--accent-crimson)'"
                       onmouseout="this.style.background='var(--blood-red)'">
                   🔐 Iniciar sesión
               </button>`;

        header.innerHTML = `
            <h1 class="logo glitch"><a href="${raiz}index.html">TERROR DIGITAL</a></h1>
            <p class="tagline">El Horror Nunca Duerme</p>
            <nav>
                <a href="${pages}noticias.html"${navActivo('noticias')}>Noticias</a>
                <a href="${pages}reviews.html"${navActivo('reviews')}>Reviews</a>
                <a href="${pages}guias.html"${navActivo('guias')}>Guías</a>
                <a href="${pages}discusiones.html"${navActivo('discusiones')}>Discusiones</a>
                <a href="${pages}comunidad.html"${navActivo('comunidad')}>Comunidad</a>
            </nav>
            <div style="margin-top:1rem;">${btnSesion}</div>
        `;
    }

    // ── Inyectar modal ────────────────────────────────────────────────────────
    function injectModal() {
        if (document.getElementById('td-auth-modal')) return;

        const modal = document.createElement('div');
        modal.id = 'td-auth-modal';
        modal.style.cssText = [
            'display:none',
            'position:fixed',
            'inset:0',
            'z-index:9999',
            'align-items:center',
            'justify-content:center',
            'background:rgba(0,0,0,.88)',
        ].join(';');

        modal.innerHTML = `
        <div style="background:linear-gradient(160deg,#0d0d0d,#1a0033);border:2px solid var(--blood-red);box-shadow:0 0 50px rgba(139,0,0,.6);width:100%;max-width:420px;padding:2.5rem;position:relative;margin:1rem;font-family:'Rubik',sans-serif;">

            <button onclick="TD_cerrarModal()" aria-label="Cerrar"
                    style="position:absolute;top:1rem;right:1rem;background:transparent;border:none;color:#888;font-size:1.6rem;cursor:pointer;line-height:1;">&times;</button>

            <!-- Tabs -->
            <div style="display:flex;margin-bottom:1.8rem;border-bottom:2px solid #2a2a2a;">
                <button id="td-tab-login"    onclick="TD_abrirModal('login')"
                        style="flex:1;padding:.8rem;border:none;font-family:'Rubik',sans-serif;font-size:.9rem;font-weight:700;text-transform:uppercase;letter-spacing:.07rem;cursor:pointer;">
                    🔐 Iniciar Sesión
                </button>
                <button id="td-tab-registro" onclick="TD_abrirModal('registro')"
                        style="flex:1;padding:.8rem;border:none;font-family:'Rubik',sans-serif;font-size:.9rem;font-weight:700;text-transform:uppercase;letter-spacing:.07rem;cursor:pointer;">
                    📝 Registrarse
                </button>
            </div>

            <!-- Alerta -->
            <div id="td-alerta" style="display:none;padding:.75rem 1rem;margin-bottom:1rem;border-left:4px solid;font-size:.88rem;"></div>

            <!-- LOGIN -->
            <div id="td-panel-login">
                <p style="color:#888;font-size:.88rem;margin-bottom:1.3rem;">Bienvenido de vuelta, superviviente</p>
                ${campo('td-login-usuario','Usuario o Email','text','username')}
                ${campo('td-login-pass','Contraseña','password','current-password','if(event.key===\'Enter\') TD_doLogin()')}
                ${boton('td-btn-login','TD_doLogin()','Entrar')}
                <p style="text-align:center;margin-top:1.1rem;color:#555;font-size:.83rem;">
                    ¿Sin cuenta? <a href="#" onclick="TD_abrirModal('registro')" style="color:var(--accent-crimson);text-decoration:none;">Regístrate gratis →</a>
                </p>
            </div>

            <!-- REGISTRO -->
            <div id="td-panel-registro" style="display:none;">
                <p style="color:#888;font-size:.88rem;margin-bottom:1.1rem;">Únete a la comunidad del terror</p>
                ${campo('td-reg-usuario','Usuario <small style="color:#555">(letras, números y _)</small>','text','username')}
                ${campo('td-reg-email','Email','email','email')}
                ${campo('td-reg-pass','Contraseña <small style="color:#555">(mín. 6 caracteres)</small>','password','new-password')}
                ${campo('td-reg-confirmar','Confirmar contraseña','password','new-password','if(event.key===\'Enter\') TD_doRegistro()')}
                ${boton('td-btn-registro','TD_doRegistro()','Crear cuenta')}
                <p style="text-align:center;margin-top:1.1rem;color:#555;font-size:.83rem;">
                    ¿Ya tienes cuenta? <a href="#" onclick="TD_abrirModal('login')" style="color:var(--accent-crimson);text-decoration:none;">Iniciar sesión →</a>
                </p>
            </div>

            <p style="text-align:center;margin-top:1.5rem;border-top:1px solid #1a1a1a;padding-top:1rem;color:#333;font-size:.78rem;">
                ¿Staff? <a href="${admin}login.php" style="color:#444;text-decoration:none;">Panel de administración →</a>
            </p>
        </div>`;

        document.body.appendChild(modal);
        modal.addEventListener('click', e => { if (e.target === modal) TD_cerrarModal(); });
    }

    // ── Helpers de construcción de HTML ──────────────────────────────────────
    const inputStyle = 'width:100%;padding:.72rem;background:#0a0a0a;border:1px solid #2a2a2a;color:#f5f5f5;font-family:\'Rubik\',sans-serif;font-size:.93rem;box-sizing:border-box;transition:border-color .2s;';

    function campo(id, label, type, autocomplete, onkeydown) {
        const kd = onkeydown ? `onkeydown="${onkeydown}"` : '';
        return `<div style="margin-bottom:1rem;">
            <label for="${id}" style="display:block;color:#bbb;font-size:.82rem;margin-bottom:.35rem;">${label}</label>
            <input id="${id}" type="${type}" autocomplete="${autocomplete}"
                   style="${inputStyle}"
                   onfocus="this.style.borderColor='var(--blood-red)'"
                   onblur="this.style.borderColor='#2a2a2a'" ${kd}>
        </div>`;
    }

    function boton(id, onclick, texto) {
        return `<button id="${id}" onclick="${onclick}"
                style="width:100%;padding:.95rem;background:var(--blood-red);border:none;color:#f5f5f5;font-family:'Rubik',sans-serif;font-size:.95rem;font-weight:700;text-transform:uppercase;letter-spacing:.1rem;cursor:pointer;transition:background .2s;margin-top:.3rem;"
                onmouseover="this.style.background='var(--accent-crimson)'"
                onmouseout="this.style.background='var(--blood-red)'">${texto}</button>`;
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Lógica del modal ──────────────────────────────────────────────────────

    function setAlerta(msg, tipo) {
        const el = document.getElementById('td-alerta');
        if (!el) return;
        el.textContent = msg;
        if (tipo === 'ok') {
            el.style.cssText = 'display:block;padding:.75rem 1rem;margin-bottom:1rem;border-left:4px solid #0c6;background:rgba(0,200,80,.08);color:#ccffdd;font-size:.88rem;';
        } else {
            el.style.cssText = 'display:block;padding:.75rem 1rem;margin-bottom:1rem;border-left:4px solid var(--accent-crimson);background:rgba(220,20,60,.08);color:#ffcccc;font-size:.88rem;';
        }
    }

    function setBtnLoading(id, loading, defaultText) {
        const btn = document.getElementById(id);
        if (!btn) return;
        btn.disabled = loading;
        btn.style.opacity = loading ? '.6' : '1';
        btn.textContent = loading ? 'Un momento...' : defaultText;
    }

    // ── API pública ───────────────────────────────────────────────────────────

    window.TD_abrirModal = function (tab) {
        const modal = document.getElementById('td-auth-modal');
        if (!modal) return;
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        const al = document.getElementById('td-alerta');
        if (al) al.style.display = 'none';

        const isLogin = tab !== 'registro';
        document.getElementById('td-panel-login').style.display    = isLogin ? 'block' : 'none';
        document.getElementById('td-panel-registro').style.display = isLogin ? 'none'  : 'block';

        const tlBtn = document.getElementById('td-tab-login');
        const trBtn = document.getElementById('td-tab-registro');
        tlBtn.style.background = isLogin ? 'var(--blood-red)' : 'transparent';
        tlBtn.style.color      = isLogin ? '#f5f5f5' : '#666';
        trBtn.style.background = isLogin ? 'transparent' : 'var(--blood-red)';
        trBtn.style.color      = isLogin ? '#666' : '#f5f5f5';

        setTimeout(() => {
            const f = document.getElementById(isLogin ? 'td-login-usuario' : 'td-reg-usuario');
            if (f) f.focus();
        }, 60);
    };

    window.TD_cerrarModal = function () {
        const modal = document.getElementById('td-auth-modal');
        if (modal) modal.style.display = 'none';
        document.body.style.overflow = '';
    };

    window.TD_doLogin = async function () {
        const usuario  = document.getElementById('td-login-usuario')?.value.trim();
        const password = document.getElementById('td-login-pass')?.value;
        if (!usuario || !password) { setAlerta('Por favor completa todos los campos.', 'error'); return; }

        setBtnLoading('td-btn-login', true, 'Entrar');
        try {
            const fd = new FormData();
            fd.append('usuario', usuario);
            fd.append('password', password);
            const res  = await fetch(`${php}login.php`, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                setAlerta(data.message, 'ok');
                setTimeout(() => window.location.reload(), 800);
            } else {
                setAlerta(data.message, 'error');
                setBtnLoading('td-btn-login', false, 'Entrar');
            }
        } catch {
            setAlerta('Error de conexión. ¿Está corriendo XAMPP?', 'error');
            setBtnLoading('td-btn-login', false, 'Entrar');
        }
    };

    window.TD_doRegistro = async function () {
        const usuario   = document.getElementById('td-reg-usuario')?.value.trim();
        const email     = document.getElementById('td-reg-email')?.value.trim();
        const password  = document.getElementById('td-reg-pass')?.value;
        const confirmar = document.getElementById('td-reg-confirmar')?.value;

        if (!usuario || !email || !password || !confirmar) {
            setAlerta('Por favor completa todos los campos.', 'error'); return;
        }
        if (password !== confirmar) {
            setAlerta('Las contraseñas no coinciden.', 'error'); return;
        }

        setBtnLoading('td-btn-registro', true, 'Crear cuenta');
        try {
            const fd = new FormData();
            fd.append('usuario',   usuario);
            fd.append('email',     email);
            fd.append('password',  password);
            fd.append('confirmar', confirmar);
            const res  = await fetch(`${php}registro.php`, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                setAlerta(data.message, 'ok');
                setTimeout(() => window.location.reload(), 900);
            } else {
                setAlerta(data.message, 'error');
                setBtnLoading('td-btn-registro', false, 'Crear cuenta');
            }
        } catch {
            setAlerta('Error de conexión. ¿Está corriendo XAMPP?', 'error');
            setBtnLoading('td-btn-registro', false, 'Crear cuenta');
        }
    };

    window.TD_logout = function () {
        window.location.href = `${php}logout.php`;
    };

    document.addEventListener('keydown', e => { if (e.key === 'Escape') TD_cerrarModal(); });

    // ── Init ──────────────────────────────────────────────────────────────────
    async function init() {
        injectModal();
        let usuario = null;
        try {
            const res  = await fetch(`${php}api_session.php`, { cache: 'no-store' });
            const data = await res.json();
            if (data.loggedIn) {
                usuario = {
                    nombre:  data.nombre,
                    esAdmin: data.rol === 'editor' || data.rol === 'admin',
                };
            }
        } catch { /* sin PHP activo → muestra botón login */ }

        injectHeader(usuario);
    }

    document.addEventListener('DOMContentLoaded', init);
})();