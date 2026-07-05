<?php
require __DIR__ . '/../app/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['ok' => false, 'error' => 'Alleen POST.'], 405);
}
auth_require_api();

foreach (['welcome_text', 'thanks_text', 'tagline', 'gallery_subtitle'] as $tekst) {
    setting_set($tekst, mb_substr(trim((string)($_POST[$tekst] ?? '')), 0, 500));
}
foreach (['upload_enabled', 'filters_enabled', 'gallery_public'] as $toggle) {
    setting_set($toggle, isset($_POST[$toggle]) ? '1' : '0');
}
header('Location: /admin/instellingen.php?opgeslagen=1');
