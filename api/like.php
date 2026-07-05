<?php
require __DIR__ . '/../app/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['ok' => false, 'error' => 'Alleen POST.'], 405);
}
if (!rate_ok('like:' . client_ip(), 120, 600)) {
    json_out(['ok' => false, 'error' => 'Even rustig aan.'], 429);
}
$input = json_decode((string)file_get_contents('php://input'), true) ?? [];
$id = (int)($input['id'] ?? 0);
$unlike = ($input['action'] ?? 'like') === 'unlike';
$likes = photo_like($id, $unlike);
if ($likes === null) {
    json_out(['ok' => false, 'error' => 'Foto niet gevonden.'], 400);
}
json_out(['ok' => true, 'likes' => $likes]);
