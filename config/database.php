<?php
ob_start();

$servername = "fdb1032.atspace.me";
$username   = "4714565_terrorblog";
$password   = "E8mVrv2JPURX3DW";
$dbname     = "4714565_terrorblog";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(['error' => 'Conexión fallida: ' . $conn->connect_error]));
}
$conn->set_charset("utf8mb4");

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=fdb1032.atspace.me;dbname=4714565_terrorblog;charset=utf8mb4',
                '4714565_terrorblog',
                'E8mVrv2JPURX3DW',
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Conexión PDO fallida: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

define('SITE_URL',         'http://terrorblog.atspace.cc');
define('SESSION_NAME',     'td_sess');
define('SESSION_LIFETIME', 7200);
define('UPLOAD_DIR',       __DIR__ . '/../uploads/');
define('UPLOAD_URL',       SITE_URL . '/uploads/');
define('MAX_FILE_SIZE',    5242880);
define('ALLOWED_IMAGES',   ['jpg','jpeg','png','gif','webp']);

date_default_timezone_set('America/Mexico_City');

function sanitize($v) {
    if (is_array($v)) return array_map('sanitize', $v);
    return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8');
}

function generateSlug($t) {
    $t = mb_strtolower($t, 'UTF-8');
    $t = strtr($t, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u']);
    $t = preg_replace('/[^a-z0-9\s-]/', '', $t);
    $t = preg_replace('/[\s-]+/', '-', $t);
    return trim($t, '-');
}

function formatDate($d) {
    $m = ['January'=>'Enero','February'=>'Febrero','March'=>'Marzo','April'=>'Abril',
          'May'=>'Mayo','June'=>'Junio','July'=>'Julio','August'=>'Agosto',
          'September'=>'Septiembre','October'=>'Octubre','November'=>'Noviembre','December'=>'Diciembre'];
    return strtr(date('d \d\e F, Y', strtotime($d)), $m);
}

function redirect($url) {
    ob_end_clean();
    header("Location: $url");
    exit();
}

function _sess() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

function setFlashMessage($type, $msg) {
    _sess();
    $_SESSION['flash'] = ['type' => $type, 'message' => $msg];
}

function getFlashMessage() {
    _sess();
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function uploadImage($file, $prefix = 'img') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK)
        return ['success'=>false,'message'=>'Error al subir el archivo'];
    if ($file['size'] > MAX_FILE_SIZE)
        return ['success'=>false,'message'=>'Archivo demasiado grande (máx 5MB)'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_IMAGES))
        return ['success'=>false,'message'=>'Tipo de archivo no permitido'];
    $name = $prefix.'_'.uniqid().'_'.time().'.'.$ext;
    if (!file_exists(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
    if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR.$name))
        return ['success'=>true,'filename'=>$name,'url'=>UPLOAD_URL.$name];
    return ['success'=>false,'message'=>'Error al mover el archivo'];
}