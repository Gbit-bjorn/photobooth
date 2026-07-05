<?php
require __DIR__ . '/../app/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['ok' => false, 'error' => 'Alleen POST.'], 405);
}
auth_require_api();

setting_set('welcome_text', mb_substr(trim((string)($_POST['welcome_text'] ?? '')), 0, 500));
foreach (['camera_enabled', 'filters_enabled', 'gallery_public'] as $toggle) {
    setting_set($toggle, isset($_POST[$toggle]) ? '1' : '0');
}
header('Location: /admin/instellingen.php?opgeslagen=1');
