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

// hartjes: per toestel onthouden welke foto's al geliket zijn
const geliked = new Set(JSON.parse(localStorage.getItem('pb-likes') ?? '[]'));

const HART_SVG = '<svg viewBox="0 0 24 24" width="19" height="19" aria-hidden="true">'
  + '<path d="M12 20.3C7 16.2 3.5 13 3.5 9.6 3.5 7 5.5 5 8 5c1.5 0 3 .8 4 2.1C13 5.8 14.5 5 16 5c2.5 0 4.5 2 4.5 4.6 0 3.4-3.5 6.6-8.5 10.7z"/></svg>';

async function toggleLike(id, knop) {
  const teller = knop.querySelector('.like-telling');
  const wasGeliked = geliked.has(id);
  const actie = wasGeliked ? 'unlike' : 'like';
  // optimistisch bijwerken
  knop.classList.toggle('geliked', !wasGeliked);
  teller.textContent = Math.max(0, parseInt(teller.textContent || '0', 10) + (wasGeliked ? -1 : 1));
  if (wasGeliked) geliked.delete(id); else geliked.add(id);
  localStorage.setItem('pb-likes', JSON.stringify([...geliked]));
  try {
    const res = await fetch('/api/like.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id, action: actie }),
    });
    const data = await res.json();
    if (data.ok) teller.textContent = data.likes;
  } catch { /* volgende poll corrigeert de teller */ }
}

async function verversLikes() {
  try {
    const res = await fetch('/api/likes.php', { cache: 'no-store' });
    const data = await res.json();
    if (!data.ok) return;
    feed.querySelectorAll('.foto-kaart').forEach(fig => {
      const stand = data.likes[fig.dataset.id];
      const teller = fig.querySelector('.like-telling');
      if (stand !== undefined && teller) teller.textContent = stand;
    });
  } catch { /* volgende poll */ }
}

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
  fig.dataset.id = photo.id;

  const voet = document.createElement('div');
  voet.className = 'kaart-voet';
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
  const hart = document.createElement('button');
  hart.type = 'button';
  hart.className = 'like-knop' + (geliked.has(photo.id) ? ' geliked' : '');
  hart.setAttribute('aria-label', 'Vind ik leuk');
  hart.innerHTML = HART_SVG + '<span class="like-telling">' + (photo.likes ?? 0) + '</span>';
  hart.addEventListener('click', () => toggleLike(photo.id, hart));
  voet.append(cap, hart);
  fig.append(voet);
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
setInterval(verversLikes, POLL_MS);
