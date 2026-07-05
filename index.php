<?php
require __DIR__ . '/app/bootstrap.php';

$ev = pb_event();
$settings = settings_all();
$config = [
    'filters'     => $settings['filters_enabled'] === '1' ? pb_filters() : [pb_filters()[0]],
    'welcomeText' => $settings['welcome_text'],
    'thanksText'  => $ev['thanks_text'],
];

page_header($ev['welcome_title'], 'page-booth');
?>
<nav class="topnav">
  <a href="/" class="active">Deel een foto</a>
  <?php if ($settings['gallery_public'] === '1'): ?><a href="/galerij.php">Galerij</a><?php endif; ?>
</nav>
<main class="wrap">
  <header>
    <h1 class="display"><?= htmlspecialchars($ev['couple']) ?></h1>
    <?php leaf_divider(); ?>
    <p class="subtitle"><?= htmlspecialchars($ev['date_display']) ?> · <?= htmlspecialchars($ev['tagline']) ?></p>
  </header>

  <section class="card" id="stap-kies">
    <p id="welkom"><?= htmlspecialchars($settings['welcome_text']) ?></p>
    <button type="button" class="btn" id="camera-knop">Neem een foto</button>
    <?php if ($settings['upload_enabled'] === '1'): ?>
    <label class="btn secondary" for="foto-input" style="margin-top: var(--space-1)">
      Kies foto's uit je galerij
      <input type="file" id="foto-input" class="visually-hidden" accept="image/*" multiple>
    </label>
    <?php endif; ?>
  </section>

  <section class="card" id="stap-bewerk" hidden>
    <div class="preview-holder"><img id="preview" alt="Jouw foto"></div>
    <div id="filter-rij" class="filter-rij" role="radiogroup" aria-label="Kies een filter"></div>
    <label for="gast-naam">Je naam (mag leeg)</label>
    <input type="text" id="gast-naam" maxlength="60" autocomplete="name">
    <label for="gast-boodschap">Boodschap voor <?= htmlspecialchars($ev['couple']) ?> (mag leeg)</label>
    <input type="text" id="gast-boodschap" maxlength="280">
    <p class="field-hint" id="meerdere-hint" hidden></p>
    <button type="button" class="btn" id="verstuur">Verstuur</button>
    <button type="button" class="btn secondary" id="annuleer">Annuleer</button>
  </section>

  <section id="stap-klaar" class="card" hidden>
    <p id="bedankt"></p>
    <button type="button" class="btn secondary" id="nog-een">Nog een foto delen</button>
  </section>

  <section id="upload-status" aria-live="polite"></section>
</main>

<div id="camera-overlay" hidden>
  <video id="camera-video" playsinline autoplay muted></video>
  <div id="camera-aftel" hidden></div>
  <div class="camera-knoppen">
    <button type="button" class="btn" id="camera-neem">Neem foto</button>
    <button type="button" class="btn secondary" id="camera-sluit">Sluit</button>
  </div>
</div>

<script>window.PB_CONFIG = <?= json_encode($config, JSON_UNESCAPED_UNICODE) ?>;</script>
<script type="module" src="/assets/js/booth.js"></script>
<?php page_footer(); ?>
