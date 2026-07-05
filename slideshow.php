<?php
require __DIR__ . '/app/bootstrap.php';
$ev = pb_event();
$s = settings_all();
if ($s['slideshow_enabled'] !== '1') {
    page_header('Slideshow');
    echo '<main class="wrap"><h1 class="display">' . htmlspecialchars($ev['couple']) . '</h1>';
    leaf_divider();
    echo '<p class="subtitle">De slideshow is momenteel niet beschikbaar.</p></main>';
    page_footer();
    exit;
}
$seconden = max(3, min(30, (int)$s['slide_seconds']));
$overgang = in_array($s['slide_transition'], ['fade', 'drift', 'zoom'], true) ? $s['slide_transition'] : 'fade';
page_header('Slideshow', 'page-slideshow');
?>
<div id="stage" class="trans-<?= $overgang ?>" style="--slide-ms: <?= $seconden * 1000 ?>ms">
  <img id="laag-a" class="slide-laag" alt="">
  <img id="laag-b" class="slide-laag" alt="">
  <div id="slide-caption" hidden>
    <strong id="cap-naam"></strong>
    <span id="cap-boodschap"></span>
  </div>
  <div id="slide-brand">
    <?php ls_monogram(); ?>
    <span class="display slide-namen"><?= htmlspecialchars($ev['couple']) ?></span>
    <span><?= htmlspecialchars($ev['date_display']) ?> · <?= htmlspecialchars($ev['short_url']) ?></span>
  </div>
  <p id="slide-leeg">Nog even geduld — de eerste foto's komen eraan…</p>
</div>
<script>window.PB_SLIDES = { ms: <?= $seconden * 1000 ?> };</script>
<script type="module" src="/assets/js/slideshow.js"></script>
<?php page_footer(); ?>
