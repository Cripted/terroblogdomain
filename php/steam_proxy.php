<?php
/**
 * php/steam_proxy.php — Terror Digital
 * Proxy para la API pública de búsqueda de Steam.
 * Evita el bloqueo CORS del navegador.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$query = trim($_GET['q'] ?? '');

if (!$query) {
    echo json_encode(['items' => []]);
    exit;
}

$url = 'https://store.steampowered.com/api/storesearch/?'
     . 'term='    . urlencode($query)
     . '&l=spanish&cc=MX';

$ctx = stream_context_create([
    'http' => [
        'timeout'       => 8,
        'ignore_errors' => true,
        'header'        => [
            'User-Agent: Mozilla/5.0 (compatible; TerrorDigital/1.0)',
        ],
    ],
    'ssl' => [
        'verify_peer'      => false,
        'verify_peer_name' => false,
    ],
]);

$raw = @file_get_contents($url, false, $ctx);

if ($raw === false) {
    http_response_code(502);
    echo json_encode(['error' => 'No se pudo conectar con Steam.']);
    exit;
}

// Reenviar la respuesta tal cual (ya es JSON de Steam)
echo $raw;