<?php
require __DIR__ . '/../app/bootstrap.php';
auth_require_page();

$ev = pb_event();
$url = 'https://' . $ev['short_url'] . '/';
page_header('QR & kaartje', 'page-admin');
?>
<nav class="topnav no-print">
  <a href="/admin/">Foto's</a>
  <a href="/admin/instellingen.php">Instellingen</a>
  <a href="/admin/qr.php" class="active">QR &amp; kaartje</a>
  <a href="/admin/logout.php">Uitloggen</a>
</nav>
<main class="wrap">
  <p class="subtitle no-print">Druk af via de printknop van je browser (Ctrl+P). Eén kaartje per pagina — kies "meerdere pagina's per vel" voor tafelkaartjes.</p>
  <div class="qr-kaartje" style="margin-top: var(--space-3)">
    <h1 class="display"><?= htmlspecialchars($ev['couple']) ?></h1>
    <p class="subtitle"><?= htmlspecialchars($ev['tagline']) ?></p>
    <div id="qr"></div>
    <p class="qr-uitleg">Scan &amp; deel jouw foto's van vandaag</p>
    <p class="qr-url"><?= htmlspecialchars($ev['short_url']) ?></p>
  </div>
  <button class="btn no-print" onclick="print()" style="margin-top: var(--space-3)">Afdrukken</button>
</main>
<script src="/assets/js/vendor/qrcode.min.js"></script>
<script>
  new QRCode(document.getElementById('qr'), {
    text: <?= json_encode($url) ?>,
    width: 220,
    height: 220,
    colorDark: '#3d4438',
    colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.M,
  });
</script>
<?php page_footer(); ?>
