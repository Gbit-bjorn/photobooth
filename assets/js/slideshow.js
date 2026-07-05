const SLIDE_MS = window.PB_SLIDES?.ms ?? 7_000;
const POLL_MS = 5_000;

let fotos = [];          // rotatielijst
let vers = [];           // nieuw binnengekomen, krijgen voorrang
let index = 0;
let latest = 0;
let actieveLaag = 'a';

const $ = id => document.getElementById(id);

async function poll() {
  try {
    const res = await fetch(`/api/photos.php?since=${latest}`, { cache: 'no-store' });
    const data = await res.json();
    if (!data.ok) return;
    latest = Math.max(latest, data.latest);
    if (latest === 0) return;
    if (fotos.length === 0 && vers.length === 0) {
      fotos = [...data.photos].reverse(); // oudste eerst als startrotatie
    } else {
      vers.push(...[...data.photos].reverse());
    }
  } catch { /* volgende poll */ }
}

function volgende() {
  if (vers.length > 0) {
    const foto = vers.shift();
    fotos.splice(index, 0, foto);
    return foto;
  }
  if (fotos.length === 0) return null;
  index = (index + 1) % fotos.length;
  return fotos[index];
}

function toon(foto) {
  if (!foto) return;
  $('slide-leeg').hidden = true;
  const binnenkomend = actieveLaag === 'a' ? $('laag-b') : $('laag-a');
  const uitgaand = actieveLaag === 'a' ? $('laag-a') : $('laag-b');
  binnenkomend.onload = () => {
    binnenkomend.classList.remove('weg');
    // reflow zodat de starttoestand (weg/rechts) vaststaat vóór de overgang
    void binnenkomend.offsetWidth;
    binnenkomend.classList.add('zichtbaar');
    uitgaand.classList.remove('zichtbaar');
    uitgaand.classList.add('weg');
    actieveLaag = actieveLaag === 'a' ? 'b' : 'a';
    const cap = $('slide-caption');
    if (foto.name || foto.message) {
      $('cap-naam').textContent = foto.name;
      $('cap-naam').hidden = !foto.name;
      $('cap-boodschap').textContent = foto.message;
      $('cap-boodschap').hidden = !foto.message;
      cap.hidden = false;
    } else {
      cap.hidden = true;
    }
  };
  binnenkomend.src = foto.src;
}

async function wakeLock() {
  try {
    if ('wakeLock' in navigator) {
      const lock = await navigator.wakeLock.request('screen');
      document.addEventListener('visibilitychange', async () => {
        if (document.visibilityState === 'visible') await navigator.wakeLock.request('screen');
      });
      return lock;
    }
  } catch { /* geen wake lock — niet fataal */ }
}

await poll();
toon(volgende());
setInterval(() => toon(volgende()), SLIDE_MS);
setInterval(poll, POLL_MS);
wakeLock();
