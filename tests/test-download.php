<?php
declare(strict_types=1);
$tmp = sys_get_temp_dir() . '/pbtest-' . bin2hex(random_bytes(4));
mkdir($tmp . '/uploads', 0777, true);
$root = dirname(__DIR__);
$port = 8125;

function ok(bool $cond, string $name): void {
    if ($cond) { echo "OK $name\n"; return; }
    echo "FAIL $name\n"; exit(1);
}

putenv("PHOTOBOOTH_DATA_DIR=$tmp");
putenv("PHOTOBOOTH_UPLOADS_DIR=$tmp/uploads");
require $root . '/app/bootstrap.php';

// seed: 3 foto's — actief, gearchiveerd, verborgen
$img = imagecreatetruecolor(100, 100);
$srcFile = $tmp . '/seed.jpg';
imagejpeg($img, $srcFile);
$a = photo_save($srcFile, 'Tante Rita', 'Veel geluk!');
$b = photo_save($srcFile, 'B', '');
$c = photo_save($srcFile, 'C', '');
photo_set_status($b['id'], 'archived');
photo_set_status($c['id'], 'hidden');

$desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$env = array_merge(getenv(), [
    'PHOTOBOOTH_DATA_DIR' => $tmp,
    'PHOTOBOOTH_UPLOADS_DIR' => $tmp . '/uploads',
]);
$server = proc_open([PHP_BINARY, '-S', "localhost:$port", '-t', $root], $desc, $pipes, $root, $env);
usleep(700_000);

try {
    // anoniem → geen zip
    $ctx = stream_context_create(['http' => ['ignore_errors' => true]]);
    $body = (string)file_get_contents("http://localhost:$port/api/download.php", false, $ctx);
    ok(!str_starts_with($body, 'PK'), 'anonymous gets no zip');

    // login
    $pw = pb_secrets()['admin_password'];
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => 'wachtwoord=' . urlencode($pw),
        'ignore_errors' => true, 'follow_location' => 0,
    ]]);
    file_get_contents("http://localhost:$port/admin/login.php", false, $ctx);
    $cookie = '';
    foreach ($http_response_header as $h) {
        if (stripos($h, 'Set-Cookie:') === 0) $cookie = trim(explode(';', substr($h, 11))[0]);
    }

    $ctx = stream_context_create(['http' => ['header' => "Cookie: $cookie", 'ignore_errors' => true]]);
    $zipBody = (string)file_get_contents("http://localhost:$port/api/download.php", false, $ctx);
    ok(str_starts_with($zipBody, 'PK'), 'zip magic bytes');

    $zipFile = $tmp . '/dl.zip';
    file_put_contents($zipFile, $zipBody);
    $zip = new ZipArchive();
    ok($zip->open($zipFile) === true, 'zip opens');
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) $names[] = $zip->getNameIndex($i);
    ok(in_array($a['filename'], $names, true) && in_array($b['filename'], $names, true), 'correct files in zip');
    ok(!in_array($c['filename'], $names, true), 'hidden photo not in zip');
    ok(in_array('wensen.html', $names, true), 'wensen.html in zip');
    $wensen = $zip->getFromName('wensen.html');
    ok(str_contains($wensen, 'Tante Rita') && str_contains($wensen, 'Veel geluk!')
        && str_contains($wensen, $a['filename']), 'wensen.html bevat naam, wens en fotoverwijzing');
    $zip->close();
} finally {
    proc_terminate($server);
    proc_close($server);
}
