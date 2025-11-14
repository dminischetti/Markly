import {
  initApi,
  loadAllNotes,
  getNoteById,
  getNoteBySlug,
  createNote,
  updateNote,
  deleteNote,
  searchNotes,
  togglePublish,
  logout,
  getBacklinks,
  ApiError
} from './api.js';
import {
  cacheNote,
  cacheNoteList,
  getCachedList,
  getCachedNote,
  getCachedNoteBySlug,
  queueOutbox,
  processOutbox,
  onOutboxChange,
  getOutbox,
  pruneOutbox,
} from './db.js';
import {
  initEditor,
  setValue as setEditorValue,
  getValue as getEditorValue,
  focusEditor,
} from './editor.js';

const boot = window.MARKLY_BOOT || { routes: {}, csrf: null };
initApi(boot.routes, boot.csrf);

const appMeta = boot.app || {};
const rawBase = typeof appMeta.base === 'string' ? appMeta.base.trim() : '';
const BASE_ABSOLUTE = rawBase.startsWith('http://') || rawBase.startsWith('https://') ? rawBase.replace(/\/$/, '') : '';
const BASE_PATH = (() => {
  if (BASE_ABSOLUTE) {
    try {
      const parsed = new URL(BASE_ABSOLUTE);
      return parsed.pathname.replace(/\/$/, '') || '';
    } catch (err) {
      return '';
    }
  }
  if (!rawBase || rawBase === '/') {
    return '';
  }
  return `/${rawBase.replace(/^\/|\/$/g, '')}`;
})();
const COOKIE_PATH = `${BASE_PATH || '/'}`.replace(/\/?$/, '/');

function baseHref(path = '') {
  if (!path) {
    if (BASE_ABSOLUTE) {
      return `${BASE_ABSOLUTE}/`;
    }
    return COOKIE_PATH;
  }

  const normalized = path.startsWith('/') ? path : `/${path}`;

  if (BASE_ABSOLUTE) {
    return `${BASE_ABSOLUTE}${normalized}`;
  }

  if (!BASE_PATH) {
    return normalized;
  }

  return `${BASE_PATH}${normalized}`;
}
const CACHE_VERSION = (boot.config && boot.config.cache_version) || 'v1.2.0';

if (appMeta.version) {
  console.info(`Markly ${appMeta.version} booted`);
}

const elements = {
  noteList: document.getElementById('noteList'),
  tagList: document.getElementById('tagList'),
  searchInput: document.getElementById('searchInput'),
  searchClear: document.getElementById('searchClear'),
  newNoteBtn: document.getElementById('newNoteBtn'),
  noteTitle: document.getElementById('noteTitle'),
  noteSlug: document.getElementById('noteSlug'),
  noteTags: document.getElementById('noteTags'),
  notePublic: document.getElementById('notePublic'),
  sidebar: document.getElementById('sidebar'),
  sidebarToggle: document.getElementById('sidebarToggle'),
  sidebarClose: document.getElementById('sidebarClose'),
  sidebarBackdrop: document.getElementById('sidebarBackdrop'),
  noteStatus: document.getElementById('noteStatus'),
  logoutBtn: document.getElementById('logoutBtn'),
  shareBtn: document.getElementById('shareBtn'),
  deleteBtn: document.getElementById('deleteBtn'),
  toastContainer: document.getElementById('toastContainer'),
  backlinks: document.getElementById('backlinks'),
};

const state = {
  allNotes: [],
  notes: [],
  tags: [],
  current: null,
  dirty: false,
  filterTag: null,
  searchTerm: '',
  offline: !navigator.onLine,
  syncing: false,
  outboxSize: 0,
  routeGuard: false,
  pendingPublic: null,
};

initEditor({
  initialValue: '',
  onChange: () => markDirty(),
  onSave: () => saveCurrentNote(),
});

setupEventListeners();
bootstrap();
registerServiceWorker();
logBuildMetadata();

async function bootstrap() {
  await loadNotes();
  const initialHash = location.hash;
  if (initialHash.startsWith('#/n/')) {
    const slug = decodeURIComponent(initialHash.replace('#/n/', ''));
    await openNoteBySlug(slug, { silent: true });
  } else if (state.notes.length > 0) {
    await openNoteById(state.notes[0].id, { silent: true });
  } else {
    createNewNote();
  }
  updateOutboxIndicator();
}

function setupEventListeners() {
  window.addEventListener('hashchange', () => {
    if (state.routeGuard) {
      state.routeGuard = false;
      return;
    }
    const hash = location.hash;
    if (hash.startsWith('#/n/')) {
      const slug = decodeURIComponent(hash.replace('#/n/', ''));
      if (slug) {
        openNoteBySlug(slug).catch(() => {});
      }
    }
  });

  elements.newNoteBtn?.addEventListener('click', createNewNote);
  elements.searchInput?.addEventListener('input', handleSearch);
  elements.searchClear?.addEventListener('click', clearSearch);
  elements.noteTitle?.addEventListener('input', markDirty);
  elements.noteSlug?.addEventListener('input', markDirty);
  elements.noteTags?.addEventListener('input', markDirty);
  elements.notePublic?.addEventListener('change', handlePublishToggle);
  elements.sidebarToggle?.addEventListener('click', () => toggleSidebar(true));
  elements.sidebarClose?.addEventListener('click', () => toggleSidebar(false));
  elements.sidebarBackdrop?.addEventListener('click', () => toggleSidebar(false));
  elements.logoutBtn?.addEventListener('click', handleLogout);
  elements.shareBtn?.addEventListener('click', () => {
    if (!state.current) return;
    elements.notePublic.checked = !elements.notePublic.checked;
    handlePublishToggle();
  });
  elements.deleteBtn?.addEventListener('click', deleteCurrentNote);

  window.addEventListener('online', handleOnline);
  window.addEventListener('offline', handleOffline);
  window.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      toggleSidebar(false);
    }
  });

  onOutboxChange(updateOutboxIndicator);
}

async function loadNotes() {
  try {
    const data = await loadAllNotes();
    const notes = (data.notes || []).map(normalizeNoteMeta);
    state.allNotes = notes;
    state.tags = data.tags || collectTags(notes);
    cacheNoteList(notes).catch(() => {});
    applyFilters();
    renderTags();
  } catch (err) {
    console.warn('Failed to load notes from API', err);
    const cached = await getCachedList();
    state.allNotes = (cached || []).map(normalizeNoteMeta);
    state.tags = collectTags(state.allNotes);
    applyFilters();
    renderTags();
  }
}

async function openNoteById(id, options = {}) {
  const data = await fetchNote(() => getNoteById(id, { useCache: true }), () => getCachedNote(id));
  if (data) {
    applyNote(data.note || data);
    if (!options.silent) {
      pushRoute(state.current.slug);
    }
  }
}

async function openNoteBySlug(slug, options = {}) {
  const data = await fetchNote(() => getNoteBySlug(slug, { useCache: true }), () => getCachedNoteBySlug(slug));
  if (data) {
    applyNote(data.note || data);
    if (!options.silent) {
      pushRoute(state.current.slug);
    }
  }
}

async function fetchNote(apiFn, cacheFn) {
  try {
    const payload = await apiFn();
    const note = payload.note || payload;
    if (note) {
      cacheNote(note).catch(() => {});
      updateCollections(note);
      return payload;
    }
  } catch (err) {
    console.warn('API note fetch failed', err);
  }
  const cached = await cacheFn();
  if (cached) {
    showToast('Loaded cached copy', 'info');
    return cached;
  }
  showToast('Note not found', 'error');
  return null;
}

function applyNote(note) {
  state.current = {
    id: note.id,
    slug: note.slug,
    title: note.title,
    tags: Array.isArray(note.tags) ? note.tags : [],
    content: note.content || '',
    is_public: Boolean(note.is_public),
    version: note.version,
  };
  state.pendingPublic = state.current.is_public;
  state.dirty = false;

  setEditorValue(state.current.content);
  elements.noteTitle.value = state.current.title || '';
  elements.noteSlug.value = state.current.slug || '';
  elements.noteTags.value = state.current.tags.join(',');
  elements.notePublic.checked = state.current.is_public;
  updateStatus('Saved');
  highlightActiveNote(state.current.id);
  toggleSidebar(false);
  focusEditor();
  renderBacklinks(state.current.slug);
}

function createNewNote() {
  state.current = {
    id: null,
    slug: '',
    title: '',
    tags: [],
    content: '',
    is_public: false,
    version: 1,
    temp: true,
  };
  state.dirty = true;
  state.pendingPublic = false;
  setEditorValue('');
  elements.noteTitle.value = '';
  elements.noteSlug.value = '';
  elements.noteTags.value = '';
  elements.notePublic.checked = false;
  highlightActiveNote(null);
  updateStatus('Draft');
  pushRoute('');
  focusEditor();
}

function markDirty() {
  state.dirty = true;
  updateStatus('Unsaved changes');
}

function updateStatus(text) {
  if (elements.noteStatus) {
    elements.noteStatus.textContent = text;
  }
}

async function saveCurrentNote() {
  if (!state.current) {
    return;
  }

  const payload = {
    title: elements.noteTitle.value.trim() || 'Untitled note',
    slug: elements.noteSlug.value.trim(),
    tags: elements.noteTags.value.trim(),
    content: getEditorValue(),
  };

  if (!state.dirty && !state.current.temp) {
    updateStatus('Saved');
    return;
  }

  updateStatus('Saving…');
  const dismissPending = showPendingToast('Saving…');

  try {
    let saved;
    if (!state.current.id || state.current.temp) {
      const wantsPublic = state.pendingPublic === true;
      if (state.offline) {
        await queueCreate({ ...payload, makePublic: wantsPublic });
        return;
      }
      saved = await createNote(payload);
      if (wantsPublic && saved?.id) {
        try {
          const result = await togglePublish(saved.id, true);
          if (result?.is_public !== undefined) {
            saved.is_public = result.is_public;
          } else {
            saved.is_public = true;
          }
        } catch (publishErr) {
          console.error('Publish after create failed', publishErr);
          showToast('Publish failed after save', 'error');
          saved.is_public = false;
          state.pendingPublic = false;
        }
      }
    } else {
      if (state.offline) {
        await queueUpdate(payload);
        return;
      }
      saved = await updateNote({
        id: state.current.id,
        title: payload.title,
        content: payload.content,
        tags: payload.tags,
        slug: payload.slug,
        version: state.current.version,
      });
    }

    if (saved) {
      cacheNote(saved).catch(() => {});
      updateCollections(saved);
      applyNote(saved);
      state.pendingPublic = Boolean(saved.is_public);
      updateStatus('Saved');
      showToast('Saved', 'success');
    }
  } catch (err) {
    if (err instanceof ApiError && err.status === 409) {
      showToast('Version conflict. Reloading…', 'error');
      if (state.current?.id) {
        await openNoteById(state.current.id);
      }
      return;
    }
    if (state.offline || (err instanceof TypeError)) {
      await queueUpdate(payload);
      return;
    }
    showToast('Save failed', 'error');
    console.error(err);
  } finally {
    dismissPending();
  }
}

async function deleteCurrentNote() {
  if (!state.current) return;
  if (!state.current.id) {
    createNewNote();
    return;
  }
  if (!confirm('Delete this note?')) {
    return;
  }
  if (state.offline) {
    await queueDelete(state.current);
    removeFromCollections(state.current.id);
    createNewNote();
    return;
  }
  try {
    await deleteNote(state.current.id);
    removeFromCollections(state.current.id);
    showToast('Deleted', 'success');
    if (state.notes.length > 0) {
      await openNoteById(state.notes[0].id);
    } else {
      createNewNote();
    }
  } catch (err) {
    showToast('Delete failed', 'error');
    console.error(err);
  }
}

async function queueCreate(payload) {
  const existingTempId = state.current?.temp && state.current.id ? state.current.id : null;
  const tempId = existingTempId || `temp-${Date.now()}`;
  await pruneOutbox((entry) => entry.action === 'create' && entry.payload?.tempId === tempId);
  const tempNote = {
    id: tempId,
    slug: payload.slug || tempId,
    title: payload.title,
    tags: payload.tags ? payload.tags.split(',').map((t) => t.trim()).filter(Boolean) : [],
    content: payload.content,
    updated_at: new Date().toISOString(),
    is_public: Boolean(payload.makePublic),
    temp: true,
  };
  updateCollections(tempNote);
  state.current = tempNote;
  state.pendingPublic = tempNote.is_public;
  await queueOutbox('create', { ...payload, tempId });
  state.dirty = false;
  updateStatus('Queued');
  showToast('Queued for sync', 'info');
  updateOutboxIndicator();
}

async function queueUpdate(payload) {
  if (!state.current?.id) {
    return;
  }
  await pruneOutbox(
    (entry) => entry.action === 'update' && String(entry.payload?.id) === String(state.current.id)
  );
  await queueOutbox('update', {
    id: state.current.id,
    title: payload.title,
    content: payload.content,
    tags: payload.tags,
    slug: payload.slug,
    version: state.current.version,
  });
  state.current.updated_at = new Date().toISOString();
  state.dirty = false;
  updateCollections(state.current);
  updateStatus('Queued');
  showToast('Queued for sync', 'info');
  updateOutboxIndicator();
}

async function queueDelete(note) {
  if (note.temp) {
    await pruneOutbox((entry) => entry.action === 'create' && entry.payload?.tempId === note.id);
    showToast('Draft removed', 'info');
    updateOutboxIndicator();
    state.pendingPublic = false;
    return;
  }

  await pruneOutbox((entry) => {
    if (!entry.payload) {
      return false;
    }
    const sameId = String(entry.payload.id) === String(note.id);
    return sameId && (entry.action === 'update' || entry.action === 'publish');
  });

  await queueOutbox('delete', { id: note.id });
  showToast('Delete queued', 'info');
  updateOutboxIndicator();
  state.pendingPublic = null;
}

async function queuePublish(note, makePublic) {
  await pruneOutbox(
    (entry) => entry.action === 'publish' && String(entry.payload?.id) === String(note.id)
  );
  await queueOutbox('publish', { id: note.id, public: makePublic });
  showToast('Publish queued', 'info');
  updateOutboxIndicator();
  state.pendingPublic = null;
}

function normalizeNoteMeta(note) {
  return {
    id: note.id,
    slug: note.slug,
    title: note.title || 'Untitled',
    tags: Array.isArray(note.tags) ? note.tags : (note.tags ? note.tags.split(',').map((t) => t.trim()).filter(Boolean) : []),
    updated_at: note.updated_at,
    is_public: Boolean(note.is_public),
  };
}

function updateCollections(note) {
  const normalized = normalizeNoteMeta(note);
  const upsert = (collection) => {
    const next = [...collection];
    const idx = next.findIndex((n) => String(n.id) === String(normalized.id));
    if (idx >= 0) {
      next[idx] = normalized;
    } else {
      next.unshift(normalized);
    }
    return next;
  };
  state.allNotes = upsert(state.allNotes);
  state.tags = collectTags(state.allNotes);
  applyFilters();
  renderTags();
}

function removeFromCollections(id) {
  state.allNotes = state.allNotes.filter((n) => String(n.id) !== String(id));
  state.tags = collectTags(state.allNotes);
  applyFilters();
  renderTags();
}

function renderNotesList() {
  if (!elements.noteList) return;
  elements.noteList.innerHTML = '';
  const template = document.getElementById('noteListItem');
  state.notes.forEach((note) => {
    const node = template.content.firstElementChild.cloneNode(true);
    node.dataset.slug = note.slug;
    node.dataset.id = note.id;
    node.querySelector('.note-item__title').textContent = note.title || 'Untitled';
    node.querySelector('.note-item__meta').textContent = formatMeta(note);
    node.addEventListener('click', () => openNoteById(note.id).catch(() => {}));
    elements.noteList.appendChild(node);
  });
  highlightActiveNote(state.current?.id);
}

function renderTags() {
  if (!elements.tagList) return;
  elements.tagList.innerHTML = '';
  state.tags.forEach((tag) => {
    const btn = document.createElement('button');
    btn.textContent = `#${tag}`;
    if (state.filterTag === tag) {
      btn.classList.add('active');
    }
    btn.addEventListener('click', () => {
      state.filterTag = state.filterTag === tag ? null : tag;
      applyFilters();
      renderTags();
    });
    elements.tagList.appendChild(btn);
  });
}

function collectTags(notes) {
  const set = new Set();
  notes.forEach((note) => {
    (note.tags || []).forEach((tag) => set.add(tag));
  });
  return Array.from(set).sort();
}

function applyFilters() {
  let filtered = [...state.allNotes];
  if (state.filterTag) {
    filtered = filtered.filter((note) => (note.tags || []).includes(state.filterTag));
  }
  if (state.searchTerm.trim()) {
    const term = state.searchTerm.trim().toLowerCase();
    filtered = filtered.filter((note) =>
      note.title.toLowerCase().includes(term) ||
      (note.tags || []).some((tag) => tag.toLowerCase().includes(term))
    );
  }
  state.notes = filtered;
  renderNotesList();
}

function formatMeta(note) {
  const parts = [];
  if (note.updated_at) {
    const date = new Date(note.updated_at);
    if (!isNaN(date.getTime())) {
      parts.push(date.toLocaleDateString());
    }
  }
  if (note.is_public) {
    parts.push('Public');
  }
  return parts.join(' · ');
}

function highlightActiveNote(id) {
  document.querySelectorAll('.note-item').forEach((el) => {
    if (id && String(el.dataset.id) === String(id)) {
      el.classList.add('active');
    } else {
      el.classList.remove('active');
    }
  });
}

function handleSearch(event) {
  state.searchTerm = event.target.value;
  if (state.offline || state.searchTerm.trim().length < 2) {
    applyFilters();
    return;
  }
  searchNotes(state.searchTerm)
    .then((res) => {
      const results = (res.body?.results || res.results || []).map(normalizeNoteMeta);
      if (results.length > 0) {
        state.notes = results;
        renderNotesList();
      }
    })
    .catch(() => applyFilters());
}

function clearSearch() {
  state.searchTerm = '';
  if (elements.searchInput) {
    elements.searchInput.value = '';
  }
  applyFilters();
}

function handlePublishToggle() {
  if (!state.current) return;
  const makePublic = elements.notePublic.checked;
  state.current.is_public = makePublic;
  state.pendingPublic = makePublic;

  if (!state.current.id || state.current.temp) {
    updateStatus('Queued');
    showToast(makePublic ? 'Will publish after save' : 'Will keep private until saved', 'info');
    return;
  }

  if (state.offline) {
    queuePublish(state.current, makePublic);
    state.current.updated_at = new Date().toISOString();
    updateCollections(state.current);
    updateStatus('Queued');
    return;
  }

  togglePublish(state.current.id, makePublic)
    .then(() => {
      state.current.updated_at = new Date().toISOString();
      updateCollections(state.current);
      updateStatus('Saved');
      showToast(makePublic ? 'Note published' : 'Note made private', 'success');
      state.pendingPublic = null;
    })
    .catch((err) => {
      elements.notePublic.checked = !makePublic;
      state.current.is_public = !makePublic;
      showToast('Publish failed', 'error');
      console.error(err);
    });
}

function handleOnline() {
  state.offline = false;
  showToast('Back online. Syncing…', 'info');
  syncOutbox();
}

function handleOffline() {
  state.offline = true;
  showToast('You are offline', 'info');
}

async function syncOutbox() {
  try {
    state.syncing = true;
    updateStatus('Syncing…');
    await processOutbox(async (entry) => {
      switch (entry.action) {
        case 'create': {
          const { tempId, makePublic, ...notePayload } = entry.payload;
          const created = await createNote(notePayload);
          if (makePublic && created?.id) {
            try {
              const result = await togglePublish(created.id, true);
              created.is_public = result?.is_public ?? true;
            } catch (err) {
              console.error('Queued publish failed', err);
            }
          }
          if (tempId) {
            replaceTemp(tempId, created);
          } else {
            updateCollections(created);
            applyNote(created);
          }
          break;
        }
        case 'update': {
          const updated = await updateNote(entry.payload);
          updateCollections(updated);
          break;
        }
        case 'delete': {
          await deleteNote(entry.payload.id);
          removeFromCollections(entry.payload.id);
          break;
        }
        case 'publish': {
          await togglePublish(entry.payload.id, entry.payload.public);
          break;
        }
      }
    });
    showToast('Synced changes', 'success');
    await loadNotes();
    if (state.current?.id) {
      await openNoteById(state.current.id, { silent: true });
    }
  } catch (err) {
    console.error('Sync error', err);
    showToast('Sync failed', 'error');
  } finally {
    state.syncing = false;
    updateStatus('Saved');
    updateOutboxIndicator();
  }
}

function replaceTemp(tempId, created) {
  if (!tempId) return;
  removeFromCollections(tempId);
  updateCollections(created);
  applyNote(created);
}

function toggleSidebar(force) {
  if (!elements.sidebar) return;
  const open = typeof force === 'boolean' ? force : !elements.sidebar.classList.contains('is-open');
  elements.sidebar.classList.toggle('is-open', open);
  elements.sidebarBackdrop?.classList.toggle('is-visible', open);
  document.body.classList.toggle('sidebar-open', open);
  if (elements.sidebarToggle) {
    elements.sidebarToggle.setAttribute('aria-expanded', String(open));
  }
}

function handleLogout() {
  logout()
    .then(() => {
      location.href = baseHref('login.php');
    })
    .catch(() => {
      location.href = baseHref('logout.php');
    });
}

function showToast(message, type = 'info', options = {}) {
  if (!elements.toastContainer) {
    return { dismiss() {} };
  }

  const toast = document.createElement('div');
  toast.className = `toast toast--${type}`;
  if (options.loading) {
    toast.classList.add('toast--loading');
  }
  toast.textContent = message;
  elements.toastContainer.appendChild(toast);

  let dismissed = false;
  const dismiss = () => {
    if (dismissed) return;
    dismissed = true;
    toast.classList.add('fade');
    setTimeout(() => toast.remove(), 220);
  };

  if (!options.sticky) {
    const duration = typeof options.duration === 'number' ? options.duration : 2600;
    setTimeout(dismiss, duration);
  }

  return { dismiss };
}

function showPendingToast(message) {
  let handle = null;
  const timer = setTimeout(() => {
    handle = showToast(message, 'info', { sticky: true, loading: true });
  }, 420);

  return () => {
    clearTimeout(timer);
    if (handle) {
      handle.dismiss();
    }
  };
}

function updateOutboxIndicator() {
  getOutbox()
    .then((items) => {
      state.outboxSize = items.length;
      if (state.outboxSize > 0) {
        updateStatus(`Queued (${state.outboxSize})`);
      } else if (!state.dirty && !state.syncing) {
        updateStatus('Saved');
      }
    })
    .catch(() => {});
}

function registerServiceWorker() {
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker
      .register(baseHref('sw.js'))
      .then(() => {
        console.info(`Markly service worker ${CACHE_VERSION} registered`);
      })
      .catch((err) => {
        console.warn('SW registration failed', err);
        showToast('Offline mode unavailable (service worker blocked)', 'warning', { duration: 4600 });
      });
  } else {
    showToast('Offline mode unavailable in this browser', 'warning', { duration: 4600 });
  }
}

function logBuildMetadata() {
  fetch(baseHref('version.json'), { cache: 'no-store' })
    .then((res) => (res.ok ? res.json() : null))
    .then((meta) => {
      if (!meta || !meta.version) {
        return;
      }
      const built = meta.built ? ` (${meta.built})` : '';
      console.info(`Markly build ${meta.version}${built}`);
    })
    .catch(() => {});
}

function pushRoute(slug) {
  state.routeGuard = true;
  if (slug) {
    location.hash = `#/n/${encodeURIComponent(slug)}`;
  } else {
    location.hash = '#';
  }
}

async function renderBacklinks(slug) {
  if (!elements.backlinks || !slug || state.offline) {
    return;
  }
  try {
    const links = await getBacklinks(slug);
    if (!links || links.length === 0) {
      elements.backlinks.innerHTML = '<p class="graph-empty">No backlinks yet.</p>';
      return;
    }
    const list = document.createElement('ul');
    list.className = 'graph-list';
    links.forEach((link) => {
      const item = document.createElement('li');
      item.textContent = `${link.title} (${link.slug})`;
      list.appendChild(item);
    });
    elements.backlinks.innerHTML = '';
    elements.backlinks.appendChild(list);
  } catch (err) {
    console.error('Backlinks failed', err);
  }
}

window.MarklyApp = {
  syncOutbox,
};
