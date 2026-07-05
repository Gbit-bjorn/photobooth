<?php
declare(strict_types=1);
$tmp = sys_get_temp_dir() . '/pbtest-' . bin2hex(random_bytes(4));
mkdir($tmp . '/uploads', 0777, true);
$root = dirname(__DIR__);
$port = 8124;

function ok(bool $cond, string $name): void {
    if ($cond) { echo "OK $name\n"; return; }
    echo "FAIL $name\n"; exit(1);
}

// Seed: één foto rechtstreeks via het domein
putenv("PHOTOBOOTH_DATA_DIR=$tmp");
putenv("PHOTOBOOTH_UPLOADS_DIR=$tmp/uploads");
require $root . '/app/bootstrap.php';
$img = imagecreatetruecolor(100, 100);
$srcFile = $tmp . '/seed.jpg';
imagejpeg($img, $srcFile);
photo_save($srcFile, 'Seed', '');

$desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$env = array_merge(getenv(), [
    'PHOTOBOOTH_DATA_DIR' => $tmp,
    'PHOTOBOOTH_UPLOADS_DIR' => $tmp . '/uploads',
]);
$server = proc_open([PHP_BINARY, '-S', "localhost:$port", '-t', $root], $desc, $pipes, $root, $env);
usleep(700_000);

function req(string $url, array $opts = []): array {
    $ctx = stream_context_create(['http' => array_merge([
        'ignore_errors' => true, 'method' => 'GET',
    ], $opts)]);
    $body = (string)file_get_contents($url, false, $ctx);
    $headers = $http_response_header ?? [];
    return [$body, $headers];
}

try {
    // niet ingelogd → 401
    [$body] = req("http://localhost:$port/api/moderate.php", [
        'method' => 'POST',
        'header' => "Content-Type: application/json",
        'content' => '{"id":1,"action":"hide"}',
    ]);
    ok((json_decode($body, true)['ok'] ?? true) === false, 'moderate rejects anonymous');

    // login → sessiecookie pakken
    $pw = pb_secrets()['admin_password'];
    [, $headers] = req("http://localhost:$port/admin/login.php", [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded",
        'content' => 'wachtwoord=' . urlencode($pw),
        'follow_location' => 0,
    ]);
    $cookie = '';
    foreach ($headers as $h) {
        if (stripos($h, 'Set-Cookie:') === 0) $cookie = trim(explode(';', substr($h, 11))[0]);
    }
    ok($cookie !== '', 'login sets session cookie');

    // csrf-token uit dashboard-html halen (data-csrf attribuut)
    [$html] = req("http://localhost:$port/admin/", ['header' => "Cookie: $cookie"]);
    ok(preg_match('/data-csrf="([0-9a-f]{64})"/', $html, $m) === 1, 'dashboard exposes csrf');
    $csrf = $m[1];

    // hide → status hidden
    [$body] = req("http://localhost:$port/api/moderate.php", [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nCookie: $cookie\r\nX-CSRF-Token: $csrf",
        'content' => '{"id":1,"action":"hide"}',
    ]);
    ok((json_decode($body, true)['ok'] ?? false) === true, 'hide ok');

    [$body] = req("http://localhost:$port/api/photos.php");
    ok(json_decode($body, true)['photos'] === [], 'hidden photo not in public feed');

    // restore → weer zichtbaar
    req("http://localhost:$port/api/moderate.php", [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nCookie: $cookie\r\nX-CSRF-Token: $csrf",
        'content' => '{"id":1,"action":"restore"}',
    ]);
    [$body] = req("http://localhost:$port/api/photos.php");
    ok(count(json_decode($body, true)['photos']) === 1, 'restore ok');

    // delete → weg
    req("http://localhost:$port/api/moderate.php", [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nCookie: $cookie\r\nX-CSRF-Token: $csrf",
        'content' => '{"id":1,"action":"delete"}',
    ]);
    [$body] = req("http://localhost:$port/api/photos.php");
    ok(json_decode($body, true)['photos'] === [], 'delete ok');

    // onbekende actie → 400
    [$body] = req("http://localhost:$port/api/moderate.php", [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nCookie: $cookie\r\nX-CSRF-Token: $csrf",
        'content' => '{"id":99,"action":"exploderen"}',
    ]);
    ok((json_decode($body, true)['ok'] ?? true) === false, 'unknown action rejected');
} finally {
    proc_terminate($server);
    proc_close($server);
}
