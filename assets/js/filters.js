// Filter-engine: één bron van waarheid (ops) → CSS-string voor live preview
// én kleurmatrix voor het definitieve bakken in canvas-pixels.
// Matrixformules volgen de W3C Filter Effects spec (zelfde wiskunde als CSS),
// dus preview en resultaat zijn visueel identiek.

const LUM_R = 0.2126, LUM_G = 0.7152, LUM_B = 0.0722;

function identity() {
  return { m: [1, 0, 0, 0, 1, 0, 0, 0, 1], o: [0, 0, 0] };
}

// combineer: eerst a, dan b  →  b∘a
function compose(b, a) {
  const m = new Array(9);
  for (let r = 0; r < 3; r++) {
    for (let c = 0; c < 3; c++) {
      m[r * 3 + c] =
        b.m[r * 3] * a.m[c] + b.m[r * 3 + 1] * a.m[3 + c] + b.m[r * 3 + 2] * a.m[6 + c];
    }
  }
  const o = [0, 1, 2].map(r =>
    b.m[r * 3] * a.o[0] + b.m[r * 3 + 1] * a.o[1] + b.m[r * 3 + 2] * a.o[2] + b.o[r]
  );
  return { m, o };
}

function lerpMatrix(target, v) {
  // identiteit → target naarmate v 0 → 1
  const id = identity();
  return {
    m: target.m.map((x, i) => id.m[i] + (x - id.m[i]) * v),
    o: target.o.map((x, i) => id.o[i] + (x - id.o[i]) * v),
  };
}

const PRIMITIVES = {
  grayscale: v => lerpMatrix({
    m: [LUM_R, LUM_G, LUM_B, LUM_R, LUM_G, LUM_B, LUM_R, LUM_G, LUM_B], o: [0, 0, 0],
  }, v),
  sepia: v => lerpMatrix({
    m: [0.393, 0.769, 0.189, 0.349, 0.686, 0.168, 0.272, 0.534, 0.131], o: [0, 0, 0],
  }, v),
  saturate: v => ({
    m: [
      LUM_R + (1 - LUM_R) * v, LUM_G * (1 - v),         LUM_B * (1 - v),
      LUM_R * (1 - v),         LUM_G + (1 - LUM_G) * v, LUM_B * (1 - v),
      LUM_R * (1 - v),         LUM_G * (1 - v),         LUM_B + (1 - LUM_B) * v,
    ],
    o: [0, 0, 0],
  }),
  brightness: v => ({ m: [v, 0, 0, 0, v, 0, 0, 0, v], o: [0, 0, 0] }),
  contrast: v => ({
    m: [v, 0, 0, 0, v, 0, 0, 0, v],
    o: [127.5 * (1 - v), 127.5 * (1 - v), 127.5 * (1 - v)],
  }),
  'hue-rotate': deg => {
    const a = (deg * Math.PI) / 180;
    const cos = Math.cos(a), sin = Math.sin(a);
    return {
      m: [
        LUM_R + cos * (1 - LUM_R) - sin * LUM_R,   LUM_G - cos * LUM_G - sin * LUM_G,         LUM_B - cos * LUM_B + sin * (1 - LUM_B),
        LUM_R - cos * LUM_R + sin * 0.143,         LUM_G + cos * (1 - LUM_G) + sin * 0.140,   LUM_B - cos * LUM_B - sin * 0.283,
        LUM_R - cos * LUM_R - sin * (1 - LUM_R),   LUM_G - cos * LUM_G + sin * LUM_G,         LUM_B + cos * (1 - LUM_B) + sin * LUM_B,
      ],
      o: [0, 0, 0],
    };
  },
};

const CSS_UNITS = { 'hue-rotate': 'deg' };

export function cssFilter(ops) {
  return (ops || [])
    .map(([name, v]) => `${name}(${v}${CSS_UNITS[name] ?? ''})`)
    .join(' ');
}

function buildMatrix(ops) {
  let acc = identity();
  for (const [name, v] of ops || []) {
    const prim = PRIMITIVES[name];
    if (prim) acc = compose(prim(v), acc);
  }
  return acc;
}

export function applyOpsToCanvas(canvas, ops) {
  if (!ops || ops.length === 0) return;
  const { m, o } = buildMatrix(ops);
  const ctx = canvas.getContext('2d');
  const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
  const d = img.data;
  for (let i = 0; i < d.length; i += 4) {
    const r = d[i], g = d[i + 1], b = d[i + 2];
    d[i]     = m[0] * r + m[1] * g + m[2] * b + o[0];
    d[i + 1] = m[3] * r + m[4] * g + m[5] * b + o[1];
    d[i + 2] = m[6] * r + m[7] * g + m[8] * b + o[2];
    // clamping doet Uint8ClampedArray zelf
  }
  ctx.putImageData(img, 0, 0);
}

// Laadt een File/Blob met correcte EXIF-orientatie.
// Moderne browsers (iOS 13.4+, Chrome 81+) passen EXIF-rotatie automatisch
// toe op <img>; createImageBitmap met imageOrientation dekt de rest af.
export async function loadOriented(file) {
  if ('createImageBitmap' in window) {
    try {
      return await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
      /* val terug op <img> */
    }
  }
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
    img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Kon afbeelding niet laden')); };
    img.src = url;
  });
}

// Special effects, toegepast ná de kleurmatrix.
export function applyFxToCanvas(canvas, fx) {
  if (!fx) return;
  const ctx = canvas.getContext('2d');
  const w = canvas.width, h = canvas.height;

  if (fx.grain > 0) {
    // filmkorrel: ruistegel als patroon over het beeld
    const tegel = document.createElement('canvas');
    tegel.width = tegel.height = 160;
    const tctx = tegel.getContext('2d');
    const data = tctx.createImageData(160, 160);
    for (let i = 0; i < data.data.length; i += 4) {
      const v = 118 + Math.random() * 20;
      data.data[i] = data.data[i + 1] = data.data[i + 2] = v;
      data.data[i + 3] = 255;
    }
    tctx.putImageData(data, 0, 0);
    ctx.save();
    ctx.globalAlpha = fx.grain * 0.5;
    ctx.globalCompositeOperation = 'overlay';
    ctx.fillStyle = ctx.createPattern(tegel, 'repeat');
    ctx.fillRect(0, 0, w, h);
    ctx.restore();
  }

  if (fx.vignette > 0) {
    const r = Math.hypot(w, h) / 2;
    const grad = ctx.createRadialGradient(w / 2, h / 2, r * 0.55, w / 2, h / 2, r);
    grad.addColorStop(0, 'rgba(20, 18, 14, 0)');
    grad.addColorStop(1, `rgba(20, 18, 14, ${fx.vignette})`);
    ctx.save();
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, w, h);
    ctx.restore();
  }
}

export async function processPhoto(file, filterOrOps, maxDim = 2400, quality = 0.85) {
  const filter = Array.isArray(filterOrOps)
    ? { ops: filterOrOps, fx: null }
    : { ops: filterOrOps?.ops ?? [], fx: filterOrOps?.fx ?? null };
  const src = await loadOriented(file);
  const w = src.width ?? src.naturalWidth;
  const h = src.height ?? src.naturalHeight;
  const scale = Math.min(1, maxDim / Math.max(w, h));
  const canvas = document.createElement('canvas');
  canvas.width = Math.max(1, Math.round(w * scale));
  canvas.height = Math.max(1, Math.round(h * scale));
  const ctx = canvas.getContext('2d');
  ctx.drawImage(src, 0, 0, canvas.width, canvas.height);
  if (src.close) src.close();
  applyOpsToCanvas(canvas, filter.ops);
  applyFxToCanvas(canvas, filter.fx);
  return new Promise((resolve, reject) => {
    canvas.toBlob(
      blob => (blob ? resolve(blob) : reject(new Error('Kon foto niet verwerken'))),
      'image/jpeg',
      quality
    );
  });
}
