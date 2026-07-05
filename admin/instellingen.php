<?php
require __DIR__ . '/../app/bootstrap.php';
auth_require_page();

$s = settings_all();
page_header('Instellingen', 'page-admin');
?>
<nav class="topnav">
  <a href="/admin/">Foto's</a>
  <a href="/admin/instellingen.php" class="active">Instellingen</a>
  <a href="/admin/qr.php">QR &amp; kaartje</a>
  <a href="/admin/logout.php">Uitloggen</a>
</nav>
<main class="wrap">
  <h1 class="display">Instellingen</h1>
  <?php leaf_divider(); ?>
  <?php if (isset($_GET['opgeslagen'])): ?>
    <p class="subtitle" style="color: var(--c-sage-deep)">Opgeslagen.</p>
  <?php endif; ?>
  <form class="card" method="post" action="/api/settings.php" style="margin-top: var(--space-3)">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

    <label for="welcome_text">Welkomsttekst op de gastenpagina</label>
    <textarea id="welcome_text" name="welcome_text" rows="3"><?= htmlspecialchars($s['welcome_text']) ?></textarea>

    <label><input type="checkbox" name="camera_enabled" <?= $s['camera_enabled'] === '1' ? 'checked' : '' ?>>
      Camera-knop tonen (foto nemen in de browser)</label>
    <label><input type="checkbox" name="filters_enabled" <?= $s['filters_enabled'] === '1' ? 'checked' : '' ?>>
      Filters aanbieden bij upload</label>
    <label><input type="checkbox" name="gallery_public" <?= $s['gallery_public'] === '1' ? 'checked' : '' ?>>
      Galerij publiek zichtbaar</label>

    <button class="btn" type="submit" style="margin-top: var(--space-2)">Opslaan</button>
  </form>
</main>
<?php page_footer(); ?>
