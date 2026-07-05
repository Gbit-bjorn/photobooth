// Camera-capture met aftelklok. Start op de selfie-camera (photobooth!)
// en kan wisselen naar de achtercamera. Preview van de front-camera wordt
// gespiegeld getoond én zo vastgelegd — de foto is wat de gast zag.

const $ = id => document.getElementById(id);
let stream = null;
let facing = 'user';

async function openCamera() {
  stream = await navigator.mediaDevices.getUserMedia({
    video: { facingMode: facing, width: { ideal: 2400 } },
    audio: false,
  });
  const video = $('camera-video');
  video.srcObject = stream;
  video.classList.toggle('gespiegeld', facing === 'user');
  $('camera-overlay').hidden = false;
}

function stopStream() {
  if (stream) {
    stream.getTracks().forEach(t => t.stop());
    stream = null;
  }
}

function sluitCamera() {
  stopStream();
  $('camera-overlay').hidden = true;
}

function aftellen(vanaf = 3) {
  return new Promise(resolve => {
    const el = $('camera-aftel');
    el.hidden = false;
    let n = vanaf;
    el.textContent = n;
    const timer = setInterval(() => {
      n -= 1;
      if (n === 0) {
        clearInterval(timer);
        el.hidden = true;
        resolve();
      } else {
        el.textContent = n;
      }
    }, 1000);
  });
}

export function initCamera(onCapture) {
  $('camera-knop').addEventListener('click', async () => {
    try {
      await openCamera();
    } catch {
      alert('Camera niet beschikbaar. Kies een foto uit je galerij.');
    }
  });
  $('camera-sluit').addEventListener('click', sluitCamera);
  $('camera-wissel').addEventListener('click', async () => {
    stopStream();
    facing = facing === 'user' ? 'environment' : 'user';
    try {
      await openCamera();
    } catch {
      // toestel heeft maar één camera: terugdraaien
      facing = facing === 'user' ? 'environment' : 'user';
      try { await openCamera(); } catch { sluitCamera(); }
    }
  });
  $('camera-neem').addEventListener('click', async () => {
    await aftellen();
    const video = $('camera-video');
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    if (facing === 'user') {
      ctx.translate(canvas.width, 0);
      ctx.scale(-1, 1);
    }
    ctx.drawImage(video, 0, 0);
    sluitCamera();
    canvas.toBlob(blob => { if (blob) onCapture(blob); }, 'image/jpeg', 0.92);
  });
}
