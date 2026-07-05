<?php
declare(strict_types=1);
$tmp = sys_get_temp_dir() . '/pbtest-' . bin2hex(random_bytes(4));
mkdir($tmp . '/uploads', 0777, true);
$root = dirname(__DIR__);
$port = 8123;

function ok(bool $cond, string $name): void {
    if ($cond) { echo "OK $name\n"; return; }
    echo "FAIL $name\n"; exit(1);
}

$desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$env = array_merge(getenv(), [
    'PHOTOBOOTH_DATA_DIR' => $tmp,
    'PHOTOBOOTH_UPLOADS_DIR' => $tmp . '/uploads',
]);
$server = proc_open(
    [PHP_BINARY, '-S', "localhost:$port", '-t', $root],
    $desc, $pipes, $root, $env
);
usleep(700_000); // server even laten opstarten

try {
    $ping = json_decode((string)file_get_contents("http://localhost:$port/api/ping.php"), true);
    ok(($ping['ok'] ?? false) === true, 'ping ok');

    // upload: maak test-jpeg en POST als multipart
    $img = imagecreatetruecolor(800, 600);
    imagefilledrectangle($img, 0, 0, 799, 599, imagecolorallocate($img, 100, 140, 100));
    $srcFile = $tmp . '/up.jpg';
    imagejpeg($img, $srcFile, 90);
    imagedestroy($img);

    $boundary = 'pb' . bin2hex(random_bytes(8));
    $body = "--$boundary\r\n"
        . "Content-Disposition: form-data; name=\"photo\"; filename=\"up.jpg\"\r\n"
        . "Content-Type: image/jpeg\r\n\r\n"
        . file_get_contents($srcFile) . "\r\n"
        . "--$boundary\r\n"
        . "Content-Disposition: form-data; name=\"guest_name\"\r\n\r\nNonkel Jef\r\n"
        . "--$boundary\r\n"
        . "Content-Disposition: form-data; name=\"message\"\r\n\r\nSanté!\r\n"
        . "--$boundary--\r\n";
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: multipart/form-data; boundary=$boundary",
        'content' => $body,
        'ignore_errors' => true,
    ]]);
    $up = json_decode((string)file_get_contents("http://localhost:$port/api/upload.php", false, $ctx), true);
    ok(($up['ok'] ?? false) === true && $up['id'] === 1, 'upload ok');

    // upload zonder bestand → 400
    $ctx2 = stream_context_create(['http' => [
        'method' => 'POST', 'ignore_errors' => true,
    ]]);
    $raw = (string)file_get_contents("http://localhost:$port/api/upload.php", false, $ctx2);
    $bad = json_decode($raw, true);
    ok(($bad['ok'] ?? true) === false, 'upload without file rejected');

    $list = json_decode((string)file_get_contents("http://localhost:$port/api/photos.php"), true);
    ok(count($list['photos']) === 1 && $list['latest'] === 1, 'photos list');
    ok($list['photos'][0]['name'] === 'Nonkel Jef', 'guest name in list');
    ok(str_starts_with($list['photos'][0]['src'], '/uploads/p_'), 'src path');

    $since = json_decode((string)file_get_contents("http://localhost:$port/api/photos.php?since=1"), true);
    ok($since['photos'] === [], 'since=1 empty');
} finally {
    proc_terminate($server);
    proc_close($server);
}
