<?php
require __DIR__ . '/../app/bootstrap.php';
auth_require_page();

$checks = [
    'PHP-versie ≥ 8.2'        => version_compare(PHP_VERSION, '8.2.0', '>='),
    'pdo_sqlite'              => extension_loaded('pdo_sqlite'),
    'gd'                      => extension_loaded('gd'),
    'zip (ZipArchive)'        => class_exists('ZipArchive'),
    'fileinfo'                => extension_loaded('fileinfo'),
    'mbstring'                => extension_loaded('mbstring'),
    'data/ beschrijfbaar'     => is_writable(dirname((string)db()->query('PRAGMA database_list')->fetch()['file'])),
    'uploads/ beschrijfbaar'  => is_writable(pb_uploads_dir()),
    'secrets.php aanwezig'    => pb_secrets() !== [],
    'admin-wachtwoord actief' => setting_get('admin_password_hash') !== '',
    'HTTPS actief'            => ($_SERVER['HTTPS'] ?? '') !== '' || PHP_SAPI === 'cli-server',
];
$uploadMax = ini_get('upload_max_filesize');
$postMax = ini_get('post_max_size');

page_header('Diagnose', 'page-admin');
?>
<nav class="topnav">
  <a href="/admin/">Foto's</a>
  <a href="/admin/diagnose.php" class="active">Diagnose</a>
</nav>
<main class="wrap">
  <h1 class="display">Diagnose</h1>
  <?php leaf_divider(); ?>
  <div class="card" style="margin-top: var(--space-3)">
    <?php foreach ($checks as $label => $pass): ?>
      <p style="margin: 0.3rem 0">
        <span style="color: var(--<?= $pass ? 'c-sage-deep' : 'c-terracotta' ?>)"><?= $pass ? '✓' : '✗' ?></span>
        <?= htmlspecialchars($label) ?>
      </p>
    <?php endforeach; ?>
    <p class="field-hint">upload_max_filesize: <?= htmlspecialchars((string)$uploadMax) ?> · post_max_size: <?= htmlspecialchars((string)$postMax) ?> (moeten ≥ 16M zijn; zo niet: aanpassen in Plesk → PHP-instellingen)</p>
  </div>
</main>
<?php page_footer(); ?>
