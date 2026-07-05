<?php
require __DIR__ . '/app/bootstrap.php';

$ev = pb_event();
$settings = settings_all();
$config = [
    'filters'     => $settings['filters_enabled'] === '1' ? pb_filters() : [pb_filters()[0]],
    'welcomeText' => $settings['welcome_text'],
    'thanksText'  => $settings['thanks_text'],
];

page_header($ev['welcome_title'], 'page-booth');
leaf_corner('left');
leaf_corner('right');
?>
<nav class="topnav">
  <a href="/" class="active">Deel een foto</a>
  <?php if ($settings['gallery_public'] === '1'): ?><a href="/galerij.php">Galerij</a><?php endif; ?>
</nav>
<main class="wrap">
  <header>
    <?php names_lockup(); ?>
    <p class="tagline"><?= htmlspecialchars($settings['tagline']) ?></p>
    <p class="date-line"><?= htmlspecialchars($ev['date_display']) ?></p>
  </header>

  <section class="card" id="stap-kies">
    <p id="welkom"><?= htmlspecialchars($settings['welcome_text']) ?></p>
    <button type="button" class="btn" id="camera-knop">Neem een foto</button>
    <label class="btn secondary" for="camera-app-input" style="margin-top: var(--space-1)">
      Gebruik je camera-app
      <input type="file" id="camera-app-input" class="visually-hidden" accept="image/*" capture="environment">
    </label>
    <?php if ($settings['upload_enabled'] === '1'): ?>
    <label class="btn secondary" for="foto-input" style="margin-top: var(--space-1)">
      Kies foto's uit je galerij
      <input type="file" id="foto-input" class="visually-hidden" accept="image/*" multiple>
    </label>
    <?php endif; ?>
  </section>

  <section class="card" id="stap-bewerk" hidden>
    <div class="preview-holder"><img id="preview" alt="Jouw foto"><div id="preview-fx"></div></div>
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
    <?php leaf_sprig(); ?>
    <p id="bedankt" style="text-align: center"></p>
    <button type="button" class="btn secondary" id="nog-een">Nog een foto delen</button>
  </section>

  <section id="upload-status" aria-live="polite"></section>
</main>

<div id="camera-overlay" hidden>
  <video id="camera-video" playsinline autoplay muted></video>
  <div id="camera-aftel" hidden></div>
  <div class="camera-knoppen">
    <button type="button" class="btn" id="camera-neem">Neem foto</button>
    <button type="button" class="btn secondary" id="camera-wissel" aria-label="Wissel tussen selfie- en achtercamera">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 12a9 9 0 0 1 15.5-6.2L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15.5 6.2L3 16"/><path d="M3 21v-5h5"/></svg>
      Draai
    </button>
    <button type="button" class="btn secondary" id="camera-sluit">Sluit</button>
  </div>
</div>

<script>window.PB_CONFIG = <?= json_encode($config, JSON_UNESCAPED_UNICODE) ?>;</script>
<script type="module" src="/assets/js/booth.js"></script>
<?php page_footer(); ?>
