<?php
require __DIR__ . '/app/bootstrap.php';
$ev = pb_event();
page_header('Slideshow', 'page-slideshow');
?>
<div id="stage">
  <img id="laag-a" class="slide-laag" alt="">
  <img id="laag-b" class="slide-laag" alt="">
  <div id="slide-caption" hidden>
    <strong id="cap-naam"></strong>
    <span id="cap-boodschap"></span>
  </div>
  <div id="slide-brand">
    <span class="display"><?= htmlspecialchars($ev['couple']) ?></span>
    <span><?= htmlspecialchars($ev['date_display']) ?> · <?= htmlspecialchars($ev['short_url']) ?></span>
  </div>
  <p id="slide-leeg">Nog even geduld — de eerste foto's komen eraan…</p>
</div>
<script type="module" src="/assets/js/slideshow.js"></script>
<?php page_footer(); ?>
