<?php
require __DIR__ . '/../app/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['ok' => false, 'error' => 'Alleen POST.'], 405);
}
if (!rate_ok(client_ip())) {
    json_out(['ok' => false, 'error' => 'Even rustig aan — probeer zo weer.'], 429);
}
$file = $_FILES['photo'] ?? null;
if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    json_out(['ok' => false, 'error' => 'Geen foto ontvangen.'], 400);
}
if ($file['size'] > 15 * 1024 * 1024) {
    json_out(['ok' => false, 'error' => 'Foto is te groot (max 15 MB).'], 400);
}
$origineel = $_FILES['original'] ?? null;
$origineelTmp = null;
$origineelNaam = '';
if ($origineel !== null && ($origineel['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
    && $origineel['size'] <= 30 * 1024 * 1024) {
    $origineelTmp = $origineel['tmp_name'];
    $origineelNaam = (string)$origineel['name'];
}
try {
    $saved = photo_save(
        $file['tmp_name'],
        (string)($_POST['guest_name'] ?? ''),
        (string)($_POST['message'] ?? ''),
        $origineelTmp,
        $origineelNaam
    );
} catch (InvalidArgumentException $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 400);
}
json_out(['ok' => true, 'id' => $saved['id']]);
