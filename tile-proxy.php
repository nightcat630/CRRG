<?php
// Leaflet 瓦片代理
$z = (int)($_GET['z'] ?? 0);
$x = (int)($_GET['x'] ?? 0);
$y = (int)($_GET['y'] ?? 0);
$s = ['a','b','c'][($x + $y) % 3];
$url = "https://{$s}.tile.openstreetmap.org/{$z}/{$x}/{$y}.png";

$ctx = stream_context_create([
    'http' => [
        'timeout' => 5,
        'header' => "User-Agent: crrg-moebius.com/1.0 (TileProxy; admin@crrg-moebius.com)
Referer: https://crrg-moebius.com/
"
    ],
    'ssl' => ['verify_peer' => false]
]);
$data = @file_get_contents($url, false, $ctx);
if ($data) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    echo $data;
} else {
    http_response_code(404);
}
