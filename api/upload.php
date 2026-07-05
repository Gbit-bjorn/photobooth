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
try {
    $saved = photo_save(
        $file['tmp_name'],
        (string)($_POST['guest_name'] ?? ''),
        (string)($_POST['message'] ?? '')
    );
} catch (InvalidArgumentException $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 400);
}
json_out(['ok' => true, 'id' => $saved['id']]);
