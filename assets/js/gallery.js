// Feed: eerste load haalt alles op, daarna polling op ?since= en prepend.
const POLL_MS = 15_000;
let latest = 0;

function kaart(photo) {
  const fig = document.createElement('figure');
  fig.className = 'foto-kaart';
  const img = document.createElement('img');
  img.src = photo.thumb;
  img.alt = photo.message || 'Gedeelde foto';
  img.loading = 'lazy';
  img.addEventListener('click', () => { window.open(photo.src, '_blank'); });
  fig.append(img);
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
    const feed = document.getElementById('feed');
    // photos komen nieuwste-eerst; bij prepend in omgekeerde volgorde invoegen
    for (const photo of [...data.photos].reverse()) {
      feed.prepend(kaart(photo));
    }
    document.getElementById('leeg').hidden = feed.children.length > 0;
  } catch {
    /* volgende poll probeert opnieuw */
  }
}

await haalOp();
setInterval(haalOp, POLL_MS);
