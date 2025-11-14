const PREFIX = (window.MARKLY_BOOT && window.MARKLY_BOOT.app && window.MARKLY_BOOT.app.storage_prefix) || 'mdpro_';
const DB_NAME = `${PREFIX}markly-app`;
const DB_VERSION = 1;
const NOTE_LIST_KEY = `${PREFIX}notes:list`;

const listeners = {
  outbox: new Set(),
};

const memoryFallback = {
  notes: new Map(),
  list: [],
  outbox: [],
};

function isSupported() {
  return typeof indexedDB !== 'undefined';
}

function openDb() {
  if (!isSupported()) {
    return Promise.reject(new Error('indexedDB not supported'));
  }
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VERSION);
    req.onupgradeneeded = (event) => {
      const db = req.result;
      if (!db.objectStoreNames.contains('notes')) {
        const notesStore = db.createObjectStore('notes', { keyPath: 'id' });
        notesStore.createIndex('slug', 'slug', { unique: true });
        notesStore.createIndex('updated_at', 'updated_at');
      }
      if (!db.objectStoreNames.contains('lists')) {
        db.createObjectStore('lists', { keyPath: 'key' });
      }
      if (!db.objectStoreNames.contains('outbox')) {
        db.createObjectStore('outbox', { keyPath: 'id', autoIncrement: true });
      }
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

async function withStore(storeName, mode, callback) {
  if (!isSupported()) {
    return callback(memoryFallback, true);
  }
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(storeName, mode);
    const store = tx.objectStore(storeName);
    const result = callback(store, false);
    tx.oncomplete = () => {
      if (result && typeof result === 'object' && 'result' in result) {
        resolve(result.result);
      } else {
        resolve(result);
      }
    };
    tx.onerror = () => reject(tx.error);
  });
}

export async function cacheNote(note) {
  if (!note || !note.id) return;
  if (!isSupported()) {
    memoryFallback.notes.set(note.id, note);
    return;
  }
  await withStore('notes', 'readwrite', (store) => {
    store.put(note);
  });
}

export async function cacheNoteList(notes) {
  if (!isSupported()) {
    memoryFallback.list = notes;
    return;
  }
  await withStore('lists', 'readwrite', (store) => {
    store.put({ key: NOTE_LIST_KEY, value: notes, savedAt: Date.now() });
  });
}

export async function getCachedNote(id) {
  if (!isSupported()) {
    return memoryFallback.notes.get(id) || null;
  }
  return withStore('notes', 'readonly', (store) => store.get(id));
}

export async function getCachedNoteBySlug(slug) {
  if (!isSupported()) {
    for (const note of memoryFallback.notes.values()) {
      if (note.slug === slug) return note;
    }
    return null;
  }
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction('notes', 'readonly');
    const store = tx.objectStore('notes');
    const index = store.index('slug');
    const req = index.get(slug);
    req.onsuccess = () => resolve(req.result || null);
    req.onerror = () => reject(req.error);
  });
}

export async function getCachedList() {
  if (!isSupported()) {
    return memoryFallback.list;
  }
  const record = await withStore('lists', 'readonly', (store) => store.get(NOTE_LIST_KEY));
  return record?.value || [];
}

export async function queueOutbox(action, payload) {
  if (!isSupported()) {
    const id = Date.now();
    memoryFallback.outbox.push({ id, action, payload, createdAt: Date.now() });
    notify('outbox');
    return id;
  }
  const id = await withStore('outbox', 'readwrite', (store) => store.add({ action, payload, createdAt: Date.now() }));
  notify('outbox');
  return id;
}

export async function getOutbox() {
  if (!isSupported()) {
    return [...memoryFallback.outbox].sort((a, b) => a.createdAt - b.createdAt);
  }
  return withStore('outbox', 'readonly', (store) => {
    return new Promise((resolve, reject) => {
      const items = [];
      const req = store.openCursor();
      req.onsuccess = () => {
        const cursor = req.result;
        if (cursor) {
          items.push(cursor.value);
          cursor.continue();
        } else {
          resolve(items);
        }
      };
      req.onerror = () => reject(req.error);
    });
  });
}

export async function removeOutbox(id) {
  if (!isSupported()) {
    memoryFallback.outbox = memoryFallback.outbox.filter((entry) => entry.id !== id);
    notify('outbox');
    return;
  }
  await withStore('outbox', 'readwrite', (store) => {
    store.delete(id);
  });
  notify('outbox');
}

export async function clearOutbox() {
  if (!isSupported()) {
    memoryFallback.outbox = [];
    notify('outbox');
    return;
  }
  await withStore('outbox', 'readwrite', (store) => store.clear());
  notify('outbox');
}

export async function processOutbox(processor) {
  const items = await getOutbox();
  for (const item of items) {
    try {
      await processor(item);
      await removeOutbox(item.id);
    } catch (err) {
      console.error('Outbox processing failed', err);
      throw err;
    }
  }
}

export function onOutboxChange(callback) {
  listeners.outbox.add(callback);
  return () => listeners.outbox.delete(callback);
}

export async function pruneOutbox(predicate) {
  if (typeof predicate !== 'function') {
    return;
  }

  if (!isSupported()) {
    memoryFallback.outbox = memoryFallback.outbox.filter((entry) => !predicate(entry));
    notify('outbox');
    return;
  }

  const db = await openDb();
  await new Promise((resolve, reject) => {
    const tx = db.transaction('outbox', 'readwrite');
    const store = tx.objectStore('outbox');
    const req = store.openCursor();
    req.onsuccess = () => {
      const cursor = req.result;
      if (cursor) {
        if (predicate(cursor.value)) {
          cursor.delete();
        }
        cursor.continue();
      } else {
        resolve();
      }
    };
    req.onerror = () => reject(req.error);
  });
  notify('outbox');
}

function notify(type) {
  if (!listeners[type]) return;
  listeners[type].forEach((cb) => {
    try {
      cb();
    } catch (err) {
      console.error('Listener error', err);
    }
  });
}
