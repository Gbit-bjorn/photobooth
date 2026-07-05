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
  <div id="feed" aria-live="polite"></div>
  <p id="leeg" class="subtitle" hidden>Nog geen foto's — deel de eerste!</p>
</main>
<script type="module" src="/assets/js/gallery.js"></script>
<?php page_footer(); ?>
