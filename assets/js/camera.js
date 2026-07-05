// Camera-capture met aftelklok. Alleen actief als de beheerder de
// camera-instelling aanzet (cfg.cameraEnabled → booth.js roept initCamera aan).

const $ = id => document.getElementById(id);
let stream = null;

async function openCamera() {
  stream = await navigator.mediaDevices.getUserMedia({
    video: { facingMode: 'environment', width: { ideal: 2000 } },
    audio: false,
  });
  $('camera-video').srcObject = stream;
  $('camera-overlay').hidden = false;
}

function sluitCamera() {
  if (stream) {
    stream.getTracks().forEach(t => t.stop());
    stream = null;
  }
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
  $('camera-neem').addEventListener('click', async () => {
    await aftellen();
    const video = $('camera-video');
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    sluitCamera();
    canvas.toBlob(blob => { if (blob) onCapture(blob); }, 'image/jpeg', 0.92);
  });
}
