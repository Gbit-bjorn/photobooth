// Feed: eerste load haalt alles op, daarna polling op ?since= en prepend.
// Weergaven: 'feed' (grote kaarten) of 'raster' (compact grid), onthouden
// in localStorage. Tik op een foto opent de GLightbox-slider (swipe).
const POLL_MS = 15_000;
let latest = 0;
let lightbox = null;

const feed = document.getElementById('feed');

function kaart(photo) {
  const fig = document.createElement('figure');
  fig.className = 'foto-kaart';
  const link = document.createElement('a');
  link.href = photo.src;
  link.className = 'glightbox';
  link.dataset.gallery = 'trouw';
  const beschrijving = [photo.name, photo.message].filter(Boolean).join(' — ');
  if (beschrijving) link.dataset.description = beschrijving;
  const img = document.createElement('img');
  img.src = photo.thumb;
  img.alt = photo.message || 'Gedeelde foto';
  img.loading = 'lazy';
  link.append(img);
  fig.append(link);
  if (photo.name || photo.message) {
    const cap = document.createElement('figcaption');
    if (photo.name) {
      const wie = document.createElement('strong');
      wie.textContent = photo.name;
      cap.append(wie);
    }
    if (photo.message) {
      const wat = document.createElement('span');
      wat.textContent = photo.message;
      cap.append(wat);
    }
    fig.append(cap);
  }
  return fig;
}

async function haalOp() {
  try {
    const res = await fetch(`/api/photos.php?since=${latest}`, { cache: 'no-store' });
    const data = await res.json();
    if (!data.ok) return;
    latest = Math.max(latest, data.latest);
    // photos komen nieuwste-eerst; bij prepend in omgekeerde volgorde invoegen
    for (const photo of [...data.photos].reverse()) {
      feed.prepend(kaart(photo));
    }
    document.getElementById('leeg').hidden = feed.children.length > 0;
    if (data.photos.length > 0) {
      if (lightbox) {
        lightbox.reload();
      } else {
        lightbox = GLightbox({ selector: '.glightbox', loop: false, touchNavigation: true });
      }
    }
  } catch {
    /* volgende poll probeert opnieuw */
  }
}

// weergave-schakelaar
const knoppen = {
  feed: document.getElementById('weergave-feed'),
  raster: document.getElementById('weergave-raster'),
};

function zetWeergave(modus) {
  feed.classList.toggle('raster', modus === 'raster');
  knoppen.feed.setAttribute('aria-pressed', modus === 'feed' ? 'true' : 'false');
  knoppen.raster.setAttribute('aria-pressed', modus === 'raster' ? 'true' : 'false');
  localStorage.setItem('pb-weergave', modus);
}

knoppen.feed.addEventListener('click', () => zetWeergave('feed'));
knoppen.raster.addEventListener('click', () => zetWeergave('raster'));
zetWeergave(localStorage.getItem('pb-weergave') === 'raster' ? 'raster' : 'feed');

await haalOp();
setInterval(haalOp, POLL_MS);
