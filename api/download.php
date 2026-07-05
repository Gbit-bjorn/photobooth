<?php
require __DIR__ . '/../app/bootstrap.php';

if (!auth_check()) {
    header('Location: /admin/login.php');
    exit;
}

$rows = array_merge(photos_list('active', 0, 10000), photos_list('archived', 0, 10000));
$zipPath = tempnam(sys_get_temp_dir(), 'pbzip');
$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::OVERWRITE);
foreach ($rows as $row) {
    $file = pb_uploads_dir() . '/' . $row['filename'];
    if (is_file($file)) {
        $zip->addFile($file, $row['filename']);
    }
    // origineel (volle resolutie, mét filter niet toegepast) in aparte map
    foreach (glob(pb_originals_dir() . '/o_' . substr($row['filename'], 2, 16) . '.*') ?: [] as $orig) {
        $zip->addFile($orig, 'originelen/' . basename($orig));
    }
}
$zip->close();

$naam = 'fotos-' . date('Y-m-d') . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $naam . '"');
header('Content-Length: ' . (string)filesize($zipPath));
readfile($zipPath);
unlink($zipPath);
