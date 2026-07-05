<?php
require __DIR__ . '/../app/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['ok' => false, 'error' => 'Alleen POST.'], 405);
}
auth_require_api();

$input = json_decode((string)file_get_contents('php://input'), true) ?? [];
$id = (int)($input['id'] ?? 0);
$action = (string)($input['action'] ?? '');

$ok = match ($action) {
    'hide'    => photo_set_status($id, 'hidden'),
    'restore' => photo_set_status($id, 'active'),
    'archive' => photo_set_status($id, 'archived'),
    'delete'  => photo_delete($id),
    default   => false,
};
if (!$ok) {
    json_out(['ok' => false, 'error' => 'Onbekende actie of foto.'], 400);
}
json_out(['ok' => true]);
