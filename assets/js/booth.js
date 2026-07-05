import { cssFilter, processPhoto } from '/assets/js/filters.js';
import { initQueue, enqueue, retryFailed } from '/assets/js/queue.js';
import { initCamera } from '/assets/js/camera.js';

const cfg = window.PB_CONFIG;
const $ = id => document.getElementById(id);

let wachtrij = [];        // File/Blob's die nog bewerkt moeten worden
let huidig = null;        // huidige File/Blob
let huidigeFilter = cfg.filters[0];
let previewUrl = null;

const STATUS_LABELS = {
  queued: 'in wachtrij…',
  uploading: 'versturen…',
  done: 'verzonden ✓',
  failed: 'mislukt',
};

function toonStap(id) {
  for (const stap of ['stap-kies', 'stap-bewerk', 'stap-klaar']) {
    $(stap).hidden = stap !== id;
  }
}

function renderStatus(items) {
  const box = $('upload-status');
  box.innerHTML = '';
  for (const item of items) {
    const row = document.createElement('div');
    row.className = 'upload-item';
    const label = document.createElement('span');
    label.textContent = 'Foto';
    const status = document.createElement('span');
    status.className = `status-${item.status}`;
    status.textContent = item.error ?? STATUS_LABELS[item.status];
    row.append(label, status);
    if (item.status === 'failed') {
      const retry = document.createElement('button');
      retry.className = 'btn secondary';
      retry.style.minHeight = '2rem';
      retry.style.width = 'auto';
      retry.textContent = 'Opnieuw';
      retry.addEventListener('click', () => retryFailed());
      row.append(retry);
    }
    box.append(row);
  }
}

// ruis-tegel voor de korrel-preview (svg, geen extern verzoek)
const GRAIN_URL = "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='140' height='140'%3E%3Cfilter id='g'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/%3E%3CfeColorMatrix values='0 0 0 0 0.5 0 0 0 0 0.5 0 0 0 0 0.5 0 0 0 0.6 0'/%3E%3C/filter%3E%3Crect width='140' height='140' filter='url(%23g)'/%3E%3C/svg%3E\")";

function zetPreviewFx(fx) {
  const laag = $('preview-fx');
  const vignette = fx?.vignette ?? 0;
  const grain = fx?.grain ?? 0;
  laag.style.boxShadow = vignette > 0
    ? `inset 0 0 ${Math.round(vignette * 140)}px ${Math.round(vignette * 55)}px rgba(20, 18, 14, ${vignette * 0.85})`
    : 'none';
  laag.style.backgroundImage = grain > 0 ? GRAIN_URL : 'none';
  laag.style.opacity = grain > 0 ? String(grain * 0.65) : '1';
}

function renderFilters(file) {
  const rij = $('filter-rij');
  rij.innerHTML = '';
  rij.hidden = cfg.filters.length <= 1;
  const url = URL.createObjectURL(file);
  for (const f of cfg.filters) {
    const knop = document.createElement('button');
    knop.type = 'button';
    knop.className = 'filter-optie';
    knop.setAttribute('role', 'radio');
    knop.setAttribute('aria-checked', f.id === huidigeFilter.id ? 'true' : 'false');
    const img = document.createElement('img');
    img.src = url;
    img.alt = '';
    img.style.filter = cssFilter(f.ops);
    const naam = document.createElement('span');
    naam.textContent = f.label;
    knop.append(img, naam);
    knop.addEventListener('click', () => {
      huidigeFilter = f;
      $('preview').style.filter = cssFilter(f.ops);
      zetPreviewFx(f.fx);
      rij.querySelectorAll('.filter-optie').forEach(k => k.setAttribute('aria-checked', 'false'));
      knop.setAttribute('aria-checked', 'true');
    });
    rij.append(knop);
  }
}

function bewerkVolgende() {
  huidig = wachtrij.shift() ?? null;
  if (!huidig) {
    $('bedankt').textContent = cfg.thanksText;
    toonStap('stap-klaar');
    return;
  }
  huidigeFilter = cfg.filters[0];
  if (previewUrl) URL.revokeObjectURL(previewUrl);
  previewUrl = URL.createObjectURL(huidig);
  $('preview').src = previewUrl;
  $('preview').style.filter = '';
  zetPreviewFx(null);
  renderFilters(huidig);
  const hint = $('meerdere-hint');
  hint.hidden = wachtrij.length === 0;
  hint.textContent = wachtrij.length > 0 ? `Nog ${wachtrij.length} foto('s) hierna.` : '';
  toonStap('stap-bewerk');
}

for (const inputId of ['foto-input', 'camera-app-input']) {
  const input = $(inputId);
  if (input) {
    input.addEventListener('change', e => {
      wachtrij = [...e.target.files];
      e.target.value = '';
      if (wachtrij.length > 0) bewerkVolgende();
    });
  }
}

$('verstuur').addEventListener('click', async () => {
  const knop = $('verstuur');
  knop.disabled = true;
  knop.textContent = 'Bezig…';
  try {
    const naam = $('gast-naam').value.trim();
    localStorage.setItem('pb-name', naam);
    const blob = await processPhoto(huidig, huidigeFilter);
    // origineel meesturen (volle resolutie, voor de download van het koppel)
    const origineel = huidig.size <= 30 * 1024 * 1024 ? huidig : null;
    await enqueue(blob, naam, $('gast-boodschap').value.trim(), origineel, huidig.name ?? 'foto.jpg');
    $('gast-boodschap').value = '';
    bewerkVolgende();
  } catch {
    alert('Deze foto kon niet verwerkt worden. Probeer een andere.');
  } finally {
    knop.disabled = false;
    knop.textContent = 'Verstuur';
  }
});

$('annuleer').addEventListener('click', () => {
  wachtrij = [];
  toonStap('stap-kies');
});

$('nog-een').addEventListener('click', () => toonStap('stap-kies'));

$('gast-naam').value = localStorage.getItem('pb-name') ?? '';

initQueue(renderStatus);

initCamera(blob => {
  wachtrij = [blob];
  bewerkVolgende();
});
