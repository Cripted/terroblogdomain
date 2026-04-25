<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - TERROR DIGITAL</title>
    <link href="https://fonts.googleapis.com/css2?family=Creepster&family=Nosifer&family=Crimson+Text:wght@400;600;700&family=Rubik:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="grain"></div>

    <div class="container">
        <header>
            <h1 class="logo glitch"><a href="../index.html">TERROR DIGITAL</a></h1>
            <p class="tagline">El Horror Nunca Duerme</p>
            <nav>
                <a href="noticias.html">Noticias</a>
                <a href="reviews.html">Reviews</a>
                <a href="guias.html">Guías</a>
                <a href="discusiones.html">Discusiones</a>
                <a href="comunidad.html">Comunidad</a>
            </nav>
        </header>

        <div class="login-container">
            <div class="login-header">
                <h1>Iniciar Sesión</h1>
                <p>Bienvenido de vuelta</p>
            </div>

            <?php
            // ── PHP: procesar login de usuario ───────────────────────────────
            require_once '../config/auth.php';

            if ($auth->isLoggedIn()) {
                redirect('../index.html');
            }

            $errorMsg   = '';
            $successMsg = '';

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $username = sanitize($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $remember = isset($_POST['remember']);

                if (empty($username) || empty($password)) {
                    $errorMsg = 'Por favor completa todos los campos.';
                } else {
                    $result = $auth->login($username, $password, $remember);
                    if ($result['success']) {
                        // Usuario normal → redirige al index
                        redirect(SITE_URL . '/index.html');
                    } else {
                        $errorMsg = $result['message'];
                    }
                }
            }

            $flash = getFlashMessage();
            if ($flash) {
                if ($flash['type'] === 'error') $errorMsg   = $flash['message'];
                else                            $successMsg = $flash['message'];
            }

            if ($errorMsg)   echo '<div class="alert alert-error">'  . htmlspecialchars($errorMsg)   . '</div>';
            if ($successMsg) echo '<div class="alert alert-success">' . htmlspecialchars($successMsg) . '</div>';
            ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Usuario o Email:</label>
                    <input type="text" id="username" name="username"
                           required autofocus autocomplete="username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" name="password"
                           required autocomplete="current-password">
                </div>

                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Recordar sesión (30 días)</label>
                </div>

                <button type="submit" class="btn-login">Iniciar Sesión</button>
            </form>

            <hr class="login-divider">

            <div class="login-switch">
                ¿Eres administrador? <a href="../admin/login.php">Accede al panel →</a>
            </div>

            <div class="back-link" style="margin-top:1rem;">
                <a href="../index.html">← Volver al sitio</a>
            </div>
        </div>
    </div>

    <script src="../js/script.js"></script>
</body>
</html>