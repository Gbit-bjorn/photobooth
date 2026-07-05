// Feed: eerste load haalt alles op, daarna polling op ?since= en prepend.
// Weergaven: 'feed' (grote kaarten, scherpe bron via srcset) of 'raster'
// (compact grid, thumbnails). Tik op een foto opent de GLightbox-slider.
const POLL_MS = 15_000;
let latest = 0;
let lightbox = null;
let sliderOpen = false;
let verversNaSluiten = false;

const feed = document.getElementById('feed');

const esc = s => s.replace(/[&<>"']/g, c => (
  { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
));

// sizes stuurt welke bron de browser kiest: groot in feed, thumb in raster
function huidigeSizes() {
  return feed.classList.contains('raster') ? '26vw' : 'min(92vw, 36rem)';
}

function kaart(photo) {
  const fig = document.createElement('figure');
  fig.className = 'foto-kaart';
  const link = document.createElement('a');
  link.href = photo.src;
  link.className = 'glightbox';
  link.dataset.gallery = 'trouw';
  const delen = [];
  if (photo.name) delen.push('<span class="slider-naam">' + esc(photo.name) + '</span>');
  if (photo.message) delen.push('<span class="slider-boodschap">' + esc(photo.message) + '</span>');
  if (delen.length > 0) link.dataset.description = delen.join('');
  const img = document.createElement('img');
  img.src = photo.thumb;
  img.srcset = `${photo.thumb} 480w, ${photo.src} 2400w`;
  img.sizes = huidigeSizes();
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

function verversSlider() {
  if (!lightbox) {
    // moreLength: 0 = nooit inkorten op mobiel (inkorten stript de opmaak
    // waardoor naam en boodschap aan elkaar plakken)
    lightbox = GLightbox({ selector: '.glightbox', loop: false, touchNavigation: true, moreLength: 0 });
    lightbox.on('open', () => { sliderOpen = true; });
    lightbox.on('close', () => {
      sliderOpen = false;
      if (verversNaSluiten) {
        verversNaSluiten = false;
        lightbox.reload();
      }
    });
    return;
  }
  // niet herladen terwijl de gast aan het bladeren is
  if (sliderOpen) {
    verversNaSluiten = true;
  } else {
    lightbox.reload();
  }
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
    if (data.photos.length > 0) verversSlider();
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
  const sizes = huidigeSizes();
  feed.querySelectorAll('img').forEach(img => { img.sizes = sizes; });
}

knoppen.feed.addEventListener('click', () => zetWeergave('feed'));
knoppen.raster.addEventListener('click', () => zetWeergave('raster'));
zetWeergave(localStorage.getItem('pb-weergave') === 'raster' ? 'raster' : 'feed');

await haalOp();
setInterval(haalOp, POLL_MS);
