<?php
$url = $_GET['url'] ?? '';

if (!$url) { http_response_code(400); exit; }

// Solo permitir dominios de Steam
$allowed = ['cdn.akamai.steamstatic.com', 'cdn.cloudflare.steamstatic.com'];
$host = parse_url($url, PHP_URL_HOST);
$permitido = false;
foreach ($allowed as $a) {
    if ($host === $a || str_ends_with($host, '.' . $a)) {
        $permitido = true; break;
    }
}
if (!$permitido) { http_response_code(403); exit; }

$data = false;

// Intentar primero con cURL (ignorando SSL en localhost)
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,   // Necesario en XAMPP local
        CURLOPT_SSL_VERIFYHOST => false,   // Necesario en XAMPP local
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_HTTPHEADER     => [
            'Accept: image/webp,image/apng,image/*,*/*;q=0.8',
            'Accept-Language: es-MX,es;q=0.9',
            'Referer: https://store.steampowered.com/',
        ],
    ]);
    $data     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$data || $httpCode !== 200) {
        $data = false; // Fallback a file_get_contents
    }
}

// Fallback: file_get_contents con SSL desactivado
if ($data === false) {
    $ctx = stream_context_create([
        'http' => [
            'timeout'        => 10,
            'ignore_errors'  => true,
            'user_agent'     => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'header'         => "Referer: https://store.steampowered.com/\r\n",
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ],
    ]);
    $data = @file_get_contents($url, false, $ctx);
}

if (!$data) {
    http_response_code(502);
    // Devolver imagen placeholder transparente 1x1 para no romper el layout
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->buffer($data);

if (!str_starts_with($mime, 'image/')) {
    http_response_code(502);
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
header('Access-Control-Allow-Origin: *');
echo $data;