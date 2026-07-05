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
}
$zip->close();

$naam = 'fotos-' . date('Y-m-d') . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $naam . '"');
header('Content-Length: ' . (string)filesize($zipPath));
readfile($zipPath);
unlink($zipPath);
