<?php
require __DIR__ . '/app/bootstrap.php';

$ev = pb_event();
$settings = settings_all();
if ($settings['gallery_public'] !== '1') {
    page_header('Galerij');
    echo '<main class="wrap"><h1 class="display">' . htmlspecialchars($ev['couple']) . '</h1>';
    leaf_divider();
    echo '<p class="subtitle">De galerij is momenteel niet beschikbaar.</p></main>';
    page_footer();
    exit;
}

page_header('Galerij', 'page-gallery');
leaf_corner('left');
leaf_corner('right');
?>
<nav class="topnav">
  <a href="/">Deel een foto</a>
  <a href="/galerij.php" class="active">Galerij</a>
</nav>
<main class="wrap">
  <header>
    <?php names_lockup('lockup-klein'); ?>
    <p class="tagline"><?= htmlspecialchars($settings['gallery_subtitle']) ?></p>
  </header>
  <div class="weergave-keuze" role="group" aria-label="Weergave">
    <button type="button" id="weergave-feed" aria-pressed="true" title="Grote weergave">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="4" y="3" width="16" height="12" rx="1"/><line x1="4" y1="19" x2="20" y2="19"/><line x1="4" y1="22" x2="14" y2="22"/></svg>
      <span>Feed</span>
    </button>
    <button type="button" id="weergave-raster" aria-pressed="false" title="Compact raster">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><rect x="13" y="13" width="8" height="8" rx="1"/></svg>
      <span>Raster</span>
    </button>
  </div>
  <div id="feed" aria-live="polite"></div>
  <p id="leeg" class="subtitle" hidden>Nog geen foto's — deel de eerste!</p>
</main>
<link rel="stylesheet" href="/assets/css/glightbox.min.css">
<script src="/assets/js/vendor/glightbox.min.js"></script>
<script type="module" src="/assets/js/gallery.js"></script>
<?php page_footer(); ?>
