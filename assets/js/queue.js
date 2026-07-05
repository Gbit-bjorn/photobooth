// Offline-vriendelijke upload-wachtrij: IndexedDB + sequentiële uploader
// met backoff. Overleeft pagina-refresh (blobs staan in IndexedDB).

const DB_NAME = 'pb-booth';
const STORE = 'queue';
const MAX_BACKOFF = 30_000;

let db = null;
let notify = () => {};
let running = false;
let wakeup = null; // resolve-fn om backoff-slaap te onderbreken

function openDb() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, 1);
    req.onupgradeneeded = () => {
      req.result.createObjectStore(STORE, { keyPath: 'id', autoIncrement: true });
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

function tx(mode, fn) {
  return new Promise((resolve, reject) => {
    const t = db.transaction(STORE, mode);
    const store = t.objectStore(STORE);
    const result = fn(store);
    t.oncomplete = () => resolve(result?.result ?? result);
    t.onerror = () => reject(t.error);
  });
}

async function allItems() {
  return await tx('readonly', s => s.getAll());
}

async function emit() {
  const items = await allItems();
  notify(items.map(({ id, status, error }) => ({ id, status, error: error ?? null })));
}

async function setItem(item) {
  await tx('readwrite', s => s.put(item));
}

async function removeItem(id) {
  await tx('readwrite', s => s.delete(id));
}

function sleep(ms) {
  return new Promise(resolve => {
    const timer = setTimeout(() => { wakeup = null; resolve(); }, ms);
    wakeup = () => { clearTimeout(timer); wakeup = null; resolve(); };
  });
}

async function uploadOne(item) {
  const form = new FormData();
  form.append('photo', item.blob, 'foto.jpg');
  form.append('guest_name', item.guestName);
  form.append('message', item.message);
  if (item.original) {
    form.append('original', item.original, item.originalName || 'origineel.jpg');
  }
  const res = await fetch('/api/upload.php', { method: 'POST', body: form });
  if (res.ok) return { ok: true };
  if (res.status >= 400 && res.status < 500 && res.status !== 429) {
    let msg = 'Foto geweigerd.';
    try { msg = (await res.json()).error ?? msg; } catch { /* hou default */ }
    return { ok: false, permanent: true, error: msg };
  }
  return { ok: false, permanent: false };
}

async function processLoop() {
  if (running) return;
  running = true;
  let backoff = 2000;
  try {
    for (;;) {
      const items = await allItems();
      const next = items.find(i => i.status === 'queued' || i.status === 'uploading');
      if (!next) break;

      next.status = 'uploading';
      await setItem(next);
      await emit();

      let result;
      try {
        result = await uploadOne(next);
      } catch {
        result = { ok: false, permanent: false };
      }

      if (result.ok) {
        next.status = 'done';
        await setItem(next);
        await emit();
        backoff = 2000;
        setTimeout(async () => { await removeItem(next.id); await emit(); }, 3000);
      } else if (result.permanent) {
        next.status = 'failed';
        next.error = result.error;
        await setItem(next);
        await emit();
      } else {
        // netwerk/serverfout: terug naar queued, wachten en opnieuw proberen
        next.status = 'queued';
        await setItem(next);
        await emit();
        await sleep(backoff);
        backoff = Math.min(backoff * 2, MAX_BACKOFF);
      }
    }
  } finally {
    running = false;
  }
}

export async function initQueue(onChange) {
  notify = onChange;
  db = await openDb();
  // items die 'uploading' bleven hangen na een refresh → terug naar queued
  const items = await allItems();
  for (const item of items) {
    if (item.status === 'uploading') {
      item.status = 'queued';
      await setItem(item);
    }
    if (item.status === 'done') await removeItem(item.id);
  }
  await emit();
  processLoop();
}

export async function enqueue(blob, guestName, message, original = null, originalName = '') {
  await tx('readwrite', s => s.add({ blob, guestName, message, original, originalName, status: 'queued' }));
  await emit();
  if (wakeup) wakeup();
  processLoop();
}

export async function retryFailed() {
  const items = await allItems();
  for (const item of items) {
    if (item.status === 'failed') {
      item.status = 'queued';
      delete item.error;
      await setItem(item);
    }
  }
  await emit();
  if (wakeup) wakeup();
  processLoop();
}
