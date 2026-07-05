<?php
require __DIR__ . '/../app/bootstrap.php';
auth_require_page();

$status = $_GET['status'] ?? 'active';
if (!in_array($status, PB_PHOTO_STATUSES, true)) {
    $status = 'active';
}
$fotos = photos_list($status);
$ev = pb_event();
$tabs = ['active' => 'Actief', 'hidden' => 'Verborgen', 'archived' => 'Archief'];

page_header('Beheer', 'page-admin');
?>
<nav class="topnav">
  <a href="/admin/" class="active">Foto's</a>
  <a href="/admin/instellingen.php">Instellingen</a>
  <a href="/admin/qr.php">QR &amp; kaartje</a>
  <a href="/slideshow.php" target="_blank">Slideshow ↗</a>
  <a href="/admin/diagnose.php">Diagnose</a>
  <a href="/api/download.php">Download alles (ZIP)</a>
  <a href="/admin/logout.php">Uitloggen</a>
</nav>
<main class="wrap wrap-breed" data-csrf="<?= htmlspecialchars(csrf_token()) ?>">
  <h1 class="display">Beheer</h1>
  <?php leaf_divider(); ?>
  <nav class="topnav">
    <?php foreach ($tabs as $key => $label): ?>
      <a href="/admin/?status=<?= $key ?>" class="<?= $key === $status ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </nav>
  <?php if ($fotos === []): ?>
    <p class="subtitle">Geen foto's in "<?= htmlspecialchars($tabs[$status]) ?>".</p>
  <?php endif; ?>
  <div class="admin-grid">
    <?php foreach ($fotos as $foto): ?>
      <div class="admin-kaart" data-id="<?= (int)$foto['id'] ?>">
        <a href="/uploads/<?= htmlspecialchars($foto['filename']) ?>" target="_blank">
          <img src="/uploads/<?= htmlspecialchars($foto['thumb']) ?>" alt="" loading="lazy">
        </a>
        <div class="admin-meta">
          <strong><?= htmlspecialchars($foto['guest_name']) ?></strong>
          <span><?= htmlspecialchars($foto['message']) ?></span>
          <span class="field-hint"><?= htmlspecialchars($foto['created_at']) ?> UTC</span>
        </div>
        <div class="admin-acties">
          <?php if ($status === 'active'): ?>
            <button class="btn secondary" data-action="hide">Verberg</button>
            <button class="btn secondary" data-action="archive">Archiveer</button>
          <?php else: ?>
            <button class="btn secondary" data-action="restore">Herstel</button>
            <button class="btn danger" data-action="delete">Wis definitief</button>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</main>
<script type="module" src="/assets/js/admin.js"></script>
<?php page_footer(); ?>
