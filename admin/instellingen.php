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
  <a href="/slideshow.php" target="_blank">Slideshow ↗</a>
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

    <label for="tagline">Ondertitel (onder de namen, ook op het tafelkaartje)</label>
    <input type="text" id="tagline" name="tagline" maxlength="120" value="<?= htmlspecialchars($s['tagline']) ?>">

    <label for="welcome_text">Welkomsttekst op de gastenpagina</label>
    <textarea id="welcome_text" name="welcome_text" rows="3"><?= htmlspecialchars($s['welcome_text']) ?></textarea>

    <label for="thanks_text">Bedanktekst na het versturen van een foto</label>
    <textarea id="thanks_text" name="thanks_text" rows="2"><?= htmlspecialchars($s['thanks_text']) ?></textarea>

    <label for="gallery_subtitle">Ondertitel van de galerij</label>
    <input type="text" id="gallery_subtitle" name="gallery_subtitle" maxlength="120" value="<?= htmlspecialchars($s['gallery_subtitle']) ?>">

    <label><input type="checkbox" name="upload_enabled" <?= $s['upload_enabled'] === '1' ? 'checked' : '' ?>>
      Bestaande foto's uploaden toestaan (bv. oude foto's uit de galerij)</label>
    <label><input type="checkbox" name="filters_enabled" <?= $s['filters_enabled'] === '1' ? 'checked' : '' ?>>
      Filters aanbieden bij upload</label>
    <label><input type="checkbox" name="gallery_public" <?= $s['gallery_public'] === '1' ? 'checked' : '' ?>>
      Galerij publiek zichtbaar</label>
    <label><input type="checkbox" name="slideshow_enabled" <?= $s['slideshow_enabled'] === '1' ? 'checked' : '' ?>>
      Slideshow beschikbaar (groot scherm)</label>

    <label for="slide_seconds">Seconden per foto in de slideshow (3–30)</label>
    <input type="number" id="slide_seconds" name="slide_seconds" min="3" max="30" step="1"
           value="<?= (int)$s['slide_seconds'] ?>" style="max-width: 7rem">

    <label for="slide_transition">Overgang tussen foto's</label>
    <select id="slide_transition" name="slide_transition">
      <option value="fade" <?= $s['slide_transition'] === 'fade' ? 'selected' : '' ?>>Vervagen (crossfade)</option>
      <option value="drift" <?= $s['slide_transition'] === 'drift' ? 'selected' : '' ?>>Zacht schuiven</option>
      <option value="zoom" <?= $s['slide_transition'] === 'zoom' ? 'selected' : '' ?>>Langzaam inzoomen (Ken Burns)</option>
    </select>

    <button class="btn" type="submit" style="margin-top: var(--space-2)">Opslaan</button>
  </form>
</main>
<?php page_footer(); ?>
