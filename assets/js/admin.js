const csrf = document.querySelector('[data-csrf]').dataset.csrf;

// weergave-schakelaar (compact raster om snel een foto te vinden)
const grid = document.querySelector('.admin-grid');
const knopNormaal = document.getElementById('weergave-normaal');
const knopRaster = document.getElementById('weergave-raster');

function zetWeergave(modus) {
  grid.classList.toggle('raster', modus === 'raster');
  knopNormaal.setAttribute('aria-pressed', modus === 'normaal' ? 'true' : 'false');
  knopRaster.setAttribute('aria-pressed', modus === 'raster' ? 'true' : 'false');
  localStorage.setItem('pb-admin-weergave', modus);
}

knopNormaal.addEventListener('click', () => zetWeergave('normaal'));
knopRaster.addEventListener('click', () => zetWeergave('raster'));
zetWeergave(localStorage.getItem('pb-admin-weergave') === 'raster' ? 'raster' : 'normaal');

document.querySelectorAll('.admin-kaart [data-action]').forEach(knop => {
  knop.addEventListener('click', async () => {
    const action = knop.dataset.action;
    if (action === 'delete' && knop.textContent !== 'Zeker?') {
      knop.textContent = 'Zeker?';
      setTimeout(() => { knop.textContent = 'Wis definitief'; }, 3000);
      return;
    }
    const kaart = knop.closest('.admin-kaart');
    knop.disabled = true;
    try {
      const res = await fetch('/api/moderate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify({ id: Number(kaart.dataset.id), action }),
      });
      const data = await res.json();
      if (data.ok) {
        kaart.remove();
      } else {
        alert(data.error ?? 'Er ging iets mis.');
        knop.disabled = false;
      }
    } catch {
      alert('Geen verbinding — probeer opnieuw.');
      knop.disabled = false;
    }
  });
});
