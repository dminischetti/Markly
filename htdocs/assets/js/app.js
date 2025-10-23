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
  removeCachedNote,
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
  undo as undoEditor,
  redo as redoEditor,
} from './editor.js';

const boot = window.MARKLY_BOOT || { routes: {}, csrf: null };
initApi(boot.routes, boot.csrf);

const appMeta = boot.app || {};
const STORAGE_PREFIX = appMeta.storage_prefix || 'mdpro_';
const THEME_KEY = appMeta.theme_key || `${STORAGE_PREFIX}theme`;
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
  sidebarBackdrop: document.getElementById('sidebarOverlay'),
  noteStatus: document.getElementById('noteStatus'),
  themeToggle: document.getElementById('themeToggle'),
  logoutBtn: document.getElementById('logoutBtn'),
  shareBtn: document.getElementById('shareBtn'),
  deleteBtn: document.getElementById('deleteBtn'),
  toastContainer: document.getElementById('toastContainer'),
  backlinks: document.getElementById('backlinks'),
  saveBtn: document.getElementById('saveBtn'),
  saveBtnLabel: document.querySelector('#saveBtn .btn__label'),
  statusPill: document.getElementById('statusCard'),
  footerPulse: document.getElementById('statusPulse'),
  focusToggle: document.getElementById('focusToggle'),
  metaDetails: document.getElementById('metadataPanel'),
  filterButtons: Array.from(document.querySelectorAll('[data-filter]')),
  themeToggleIcon: document.getElementById('themeToggleIcon'),
  undoBtn: document.getElementById('undoBtn'),
  redoBtn: document.getElementById('redoBtn'),
  settingsBtn: document.getElementById('settingsBtn'),
  brandPulse: document.getElementById('brandPulse'),
  editorTextarea: document.getElementById('editor'),
  quickActions: Array.from(document.querySelectorAll('[data-quick-action]')),
  visibilityPrivate: document.getElementById('visibilityPrivate'),
  visibilityPublic: document.getElementById('visibilityPublic'),
  notesCount: document.getElementById('notesCount'),
  tagsCount: document.getElementById('tagsCount'),
  noteEdited: document.getElementById('noteEdited'),
  noteMetrics: document.getElementById('noteMetrics'),
  copyLinkBtn: document.getElementById('copyLinkBtn'),
  stats: document.getElementById('stats'),
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
  saving: false,
  filterMode: 'all',
  focusMode: false,
};

window.marklyShell = function marklyShell() {
  return {
    theme: document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light',
    init() {
      this.applyTheme(this.theme);
      this.__themeListener = (event) => {
        if (event && event.detail) {
          this.theme = event.detail;
        } else {
          this.theme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        }
      };
      window.addEventListener('markly-theme', this.__themeListener);
      this.$watch('theme', (value) => this.applyTheme(value));
    },
    applyTheme(value) {
      const next = value === 'dark' ? 'dark' : 'light';
      document.documentElement.setAttribute('data-theme', next);
      document.documentElement.classList.toggle('dark', next === 'dark');
      document.body.dataset.theme = next;
      document.body.classList.toggle('dark', next === 'dark');
    },
    destroy() {
      if (this.__themeListener) {
        window.removeEventListener('markly-theme', this.__themeListener);
      }
    },
  };
};

window.marklyLayout = function marklyLayout() {
  return {
    compact: window.innerWidth < 1000,
    init() {
      this.sync();
      this.__resizeHandler = () => this.sync();
      window.addEventListener('resize', this.__resizeHandler);
    },
    sync() {
      this.compact = window.innerWidth < 1000;
    },
    destroy() {
      if (this.__resizeHandler) {
        window.removeEventListener('resize', this.__resizeHandler);
      }
    },
  };
};

window.collapsible = function collapsible(initial = true) {
  return {
    open: Boolean(initial),
    init() {
      const section = this.$refs.section;
      if (!section) {
        return;
      }
      if (!this.open) {
        section.style.display = 'none';
      }
      this.$watch('open', (value) => {
        if (!section) {
          return;
        }
        if (window.gsap) {
          if (value) {
            section.style.display = '';
            gsap.fromTo(
              section,
              { height: 0, opacity: 0 },
              {
                height: 'auto',
                opacity: 1,
                duration: 0.28,
                ease: 'power2.out',
                onComplete: () => {
                  section.style.height = '';
                },
              }
            );
          } else {
            gsap.to(section, {
              height: 0,
              opacity: 0,
              duration: 0.2,
              ease: 'power1.in',
              onComplete: () => {
                section.style.display = 'none';
                section.style.height = '';
              },
            });
          }
        } else {
          section.style.display = value ? '' : 'none';
        }
      });
    },
    toggle() {
      this.open = !this.open;
    },
  };
};

window.resizerState = function resizerState() {
  return {
    active: false,
    activate() {
      this.active = true;
      if (window.gsap) {
        gsap.to(this.$el, { scaleY: 1.2, duration: 0.18, ease: 'power1.out' });
      }
    },
    deactivate() {
      this.active = false;
      if (window.gsap) {
        gsap.to(this.$el, { scaleY: 1, duration: 0.18, ease: 'power1.in' });
      }
    },
  };
};

let brandPulseTween = null;

function isTempId(id) {
  return typeof id === 'string' && id.startsWith('temp-');
}

function hasValidId(value) {
  if (value === null || value === undefined) {
    return false;
  }
  if (typeof value === 'number') {
    return !Number.isNaN(value);
  }
  if (typeof value === 'string') {
    const trimmed = value.trim();
    if (trimmed === '' || trimmed === 'undefined' || trimmed === 'null') {
      return false;
    }
  }
  return true;
}

function normalizeNoteId(value) {
  if (!hasValidId(value)) {
    return null;
  }
  if (typeof value === 'string') {
    const trimmed = value.trim();
    if (/^\d+$/.test(trimmed)) {
      return Number(trimmed);
    }
    return trimmed;
  }
  return value;
}

function escapeHtml(value) {
  if (value === null || value === undefined) {
    return '';
  }
  return String(value).replace(/[&<>"']/g, (match) => {
    switch (match) {
      case '&':
        return '&amp;';
      case '<':
        return '&lt;';
      case '>':
        return '&gt;';
      case '"':
        return '&quot;';
      case "'":
        return '&#39;';
      default:
        return match;
    }
  });
}

function formatRelativeTime(value) {
  if (!value) {
    return 'Just now';
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return 'Just now';
  }
  const diff = Date.now() - date.getTime();
  const minute = 60 * 1000;
  const hour = 60 * minute;
  const day = 24 * hour;
  if (diff < 0) {
    return date.toLocaleDateString();
  }
  if (diff < minute) {
    return 'Just now';
  }
  if (diff < hour) {
    const mins = Math.round(diff / minute);
    return `${mins}m ago`;
  }
  if (diff < day) {
    const hours = Math.round(diff / hour);
    return `${hours}h ago`;
  }
  if (diff < day * 2) {
    return 'Yesterday';
  }
  return date.toLocaleDateString();
}

const savedTheme = getStoredTheme();
if (savedTheme) {
  document.documentElement.setAttribute('data-theme', savedTheme);
  document.documentElement.classList.toggle('dark', savedTheme === 'dark');
  document.body.dataset.theme = savedTheme;
  document.body.classList.toggle('dark', savedTheme === 'dark');
}

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
    await openNoteFromMeta(state.notes[0], { silent: true });
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
  elements.saveBtn?.addEventListener('click', () => {
    saveCurrentNote();
  });
  elements.searchInput?.addEventListener('input', handleSearch);
  elements.searchClear?.addEventListener('click', clearSearch);
  elements.noteTitle?.addEventListener('input', markDirty);
  elements.noteSlug?.addEventListener('input', markDirty);
  elements.noteTags?.addEventListener('input', markDirty);
  elements.notePublic?.addEventListener('change', handlePublishToggle);
  elements.sidebarToggle?.addEventListener('click', () => toggleSidebar(true));
  elements.sidebarClose?.addEventListener('click', () => toggleSidebar(false));
  elements.sidebarBackdrop?.addEventListener('click', () => toggleSidebar(false));
  elements.visibilityPrivate?.addEventListener('click', () => setVisibilityState(false));
  elements.visibilityPublic?.addEventListener('click', () => setVisibilityState(true));
  elements.themeToggle?.addEventListener('click', toggleTheme);
  elements.logoutBtn?.addEventListener('click', handleLogout);
  elements.undoBtn?.addEventListener('click', handleUndo);
  elements.redoBtn?.addEventListener('click', handleRedo);
  if (elements.settingsBtn) {
    const isOpen = getMetadataState();
    elements.settingsBtn.setAttribute('aria-pressed', isOpen ? 'true' : 'false');
    elements.settingsBtn.addEventListener('click', () => toggleMetadataPanel());
  }
  const metadataToggle = elements.metaDetails?.querySelector('button');
  metadataToggle?.addEventListener('click', (event) => {
    event.preventDefault();
    toggleMetadataPanel();
  });
  elements.shareBtn?.addEventListener('click', () => {
    if (!state.current) return;
    elements.notePublic.checked = !elements.notePublic.checked;
    handlePublishToggle();
  });
  elements.deleteBtn?.addEventListener('click', deleteCurrentNote);
  elements.copyLinkBtn?.addEventListener('click', copyCurrentLink);
  if (elements.focusToggle) {
    elements.focusToggle.setAttribute('aria-pressed', 'false');
  }
  elements.focusToggle?.addEventListener('click', () => toggleFocusMode());
  elements.filterButtons?.forEach((button) => {
    button.addEventListener('click', () => setFilterMode(button.dataset.filter));
  });
  elements.quickActions?.forEach((button) => {
    button.addEventListener('click', () => handleQuickAction(button.dataset.quickAction));
  });

  elements.saveBtn?.addEventListener('animationend', (event) => {
    if (event.animationName === 'btnSuccessPulse') {
      event.currentTarget.classList.remove('btn--success');
    }
  });

  window.addEventListener('online', handleOnline);
  window.addEventListener('offline', handleOffline);
  window.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      toggleSidebar(false);
    }
    if ((event.ctrlKey || event.metaKey) && event.shiftKey && event.key.toLowerCase() === 'f') {
      event.preventDefault();
      toggleFocusMode();
    }
  });

  setFilterMode(state.filterMode);
  onOutboxChange(updateOutboxIndicator);
  updateSaveButtonState();
  toggleSearchClear(elements.searchInput?.value || '');
  setMetadataState(getMetadataState());
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

async function openNoteFromMeta(note, options = {}) {
  if (!note) {
    return;
  }
  if (hasValidId(note.id)) {
    return openNoteById(note.id, options);
  }
  if (note.slug) {
    return openNoteBySlug(note.slug, options);
  }
}

async function openNoteById(id, options = {}) {
  const normalizedId = normalizeNoteId(id);
  if (!normalizedId) {
    return;
  }
  if (isTempId(normalizedId)) {
    const loaded = await loadTempNote({ id: normalizedId }, options);
    if (!loaded) {
      showToast('Draft not available', 'error');
    }
    return;
  }
  const data = await fetchNote(
    () => getNoteById(normalizedId, { useCache: true }),
    () => getCachedNote(normalizedId)
  );
  if (data) {
    applyNote(data.note || data);
    if (!options.silent) {
      pushRoute(state.current.slug);
    }
  }
}

async function openNoteBySlug(slug, options = {}) {
  if (!slug) {
    return;
  }
  const tempMeta = state.allNotes.find((note) => note.slug === slug && isTempId(note.id));
  if (tempMeta) {
    const loaded = await loadTempNote({ id: tempMeta.id, slug }, options);
    if (!loaded) {
      showToast('Draft not available', 'error');
    }
    return;
  }
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
    if (payload && payload.cached) {
      const cached = await cacheFn();
      if (cached) {
        updateCollections(cached);
        return cached;
      }
      return null;
    }
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

async function loadTempNote(identifier, options = {}) {
  let note = null;
  if (identifier.id) {
    note = await getCachedNote(identifier.id);
  }
  if (!note && identifier.slug) {
    note = await getCachedNoteBySlug(identifier.slug);
  }
  if (!note && identifier.id) {
    note = state.allNotes.find((n) => String(n.id) === String(identifier.id)) || null;
  }
  if (!note && identifier.slug) {
    note = state.allNotes.find((n) => n.slug === identifier.slug) || null;
  }
  if (!note && identifier.id && state.current?.id === identifier.id) {
    note = state.current;
  }
  if (!note) {
    return false;
  }
  const prepared = {
    id: note.id,
    slug: note.slug || '',
    title: note.title || 'Untitled note',
    tags: Array.isArray(note.tags) ? note.tags : [],
    content: note.content || '',
    is_public: Boolean(note.is_public),
    version: note.version || 1,
    temp: true,
  };
  applyNote(prepared);
  if (!options.silent) {
    pushRoute(prepared.slug);
  }
  return true;
}

function applyNote(note) {
  const normalizedId = normalizeNoteId(note.id ?? note.note_id ?? note.noteId ?? null);
  state.current = {
    id: normalizedId,
    slug: note.slug,
    title: note.title,
    tags: Array.isArray(note.tags) ? note.tags : [],
    content: note.content || '',
    is_public: Boolean(note.is_public),
    version: note.version,
    temp: Boolean(note.temp) || isTempId(normalizedId),
    updated_at: note.updated_at || note.updatedAt || null,
  };
  state.pendingPublic = state.current.is_public;
  state.dirty = false;
  updateSaveButtonState();

  setEditorValue(state.current.content);
  updateContentStats();
  elements.noteTitle.value = state.current.title || '';
  elements.noteSlug.value = state.current.slug || '';
  elements.noteTags.value = state.current.tags.join(',');
  elements.notePublic.checked = state.current.is_public;
  syncVisibilityControls();
  updateStatus('Saved');
  updateNoteMetaDisplay(state.current);
  highlightActiveNote(state.current.id, state.current.slug);
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
    updated_at: new Date().toISOString(),
  };
  state.dirty = true;
  state.pendingPublic = false;
  updateSaveButtonState();
  setEditorValue('');
  updateContentStats();
  elements.noteTitle.value = '';
  elements.noteSlug.value = '';
  elements.noteTags.value = '';
  elements.notePublic.checked = false;
  syncVisibilityControls();
  highlightActiveNote(null, null);
  updateStatus('Draft');
  updateNoteMetaDisplay(state.current);
  pushRoute('');
  focusEditor();
}

function markDirty() {
  state.dirty = true;
  updateStatus('Unsaved changes');
  updateSaveButtonState();
  updateContentStats();
}

function updateNoteMetaDisplay(note) {
  if (elements.noteEdited) {
    const timestamp = note?.updated_at || note?.updatedAt || null;
    elements.noteEdited.textContent = `Edited ${formatRelativeTime(timestamp)}`;
  }
  updateContentStats();
}

function updateContentStats() {
  const content = typeof getEditorValue === 'function' ? getEditorValue() : state.current?.content || '';
  const trimmed = content.trim();
  const wordCount = trimmed ? trimmed.split(/\s+/).length : 0;
  const charCount = content.length;
  const lineCount = content ? content.split(/\r?\n/).length : 0;
  if (elements.noteMetrics) {
    elements.noteMetrics.textContent = `${wordCount} ${wordCount === 1 ? 'word' : 'words'}`;
  }
  if (elements.stats) {
    elements.stats.textContent = `${wordCount} ${wordCount === 1 ? 'word' : 'words'} · ${charCount} ${charCount === 1 ? 'character' : 'characters'} · ${lineCount} ${lineCount === 1 ? 'line' : 'lines'}`;
  }
}

function updateStatus(text) {
  if (!elements.noteStatus) {
    return;
  }
  elements.noteStatus.textContent = text;
  if (elements.statusPill) {
    const nextState = text.toLowerCase().replace(/[^a-z0-9]+/gi, '-');
    elements.statusPill.dataset.state = nextState;
    if (window.gsap) {
      gsap.killTweensOf(elements.statusPill);
      gsap.fromTo(
        elements.statusPill,
        { opacity: 0, y: 10 },
        { opacity: 1, y: 0, duration: 0.28, ease: 'power2.out' }
      );
    }
  }
  const lowered = text.toLowerCase();
  const brandActive = lowered.includes('saving') || lowered.includes('sync');
  const footerActive = brandActive || lowered.includes('unsaved') || lowered.includes('queued');
  setBrandPulse(brandActive);
  setFooterPulse(footerActive);
}

function updateSaveButtonState() {
  if (!elements.saveBtn) {
    return;
  }
  const shouldDisable = state.saving || (!state.dirty && !state.current?.temp);
  elements.saveBtn.disabled = shouldDisable;
  const label = elements.saveBtnLabel;
  const defaultLabel = label?.dataset?.default || 'Save';
  const nextLabel = state.saving ? 'Saving…' : defaultLabel;
  if (label) {
    label.textContent = nextLabel;
  } else {
    elements.saveBtn.textContent = nextLabel;
  }
  elements.saveBtn.setAttribute('aria-busy', state.saving ? 'true' : 'false');
}

function flashSaveSuccess() {
  if (!elements.saveBtn) {
    return;
  }
  elements.saveBtn.classList.remove('btn--success');
  void elements.saveBtn.offsetWidth;
  elements.saveBtn.classList.add('btn--success');
  if (window.gsap) {
    gsap.fromTo(
      elements.saveBtn,
      { scale: 0.96, y: 2 },
      { scale: 1, y: 0, duration: 0.32, ease: 'back.out(2)' }
    );
  }
}

function triggerThemeTransition() {
  document.documentElement.classList.add('is-theme-transitioning');
  document.body.classList.add('is-theme-transitioning');
  if (window.gsap) {
    const target = document.querySelector('.relative.min-h-screen') || document.body;
    gsap.fromTo(
      target,
      { opacity: 0.9, filter: 'saturate(0.92)' },
      { opacity: 1, filter: 'saturate(1)', duration: 0.25, ease: 'power1.out' }
    );
  }
  window.setTimeout(() => {
    document.documentElement.classList.remove('is-theme-transitioning');
    document.body.classList.remove('is-theme-transitioning');
  }, 360);
}

async function saveCurrentNote() {
  if (!state.current) {
    return;
  }
  if (state.saving) {
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
    updateSaveButtonState();
    return;
  }

  state.saving = true;
  updateSaveButtonState();
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
      flashSaveSuccess();
    }
  } catch (err) {
    if (err instanceof ApiError && err.status === 409) {
      showToast('Version conflict. Reloading…', 'error');
      if (state.current) {
        await openNoteFromMeta(state.current);
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
    state.saving = false;
    updateSaveButtonState();
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
      await openNoteFromMeta(state.notes[0]);
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
    version: 1,
    updated_at: new Date().toISOString(),
    is_public: Boolean(payload.makePublic),
    temp: true,
  };
  updateCollections(tempNote);
  state.current = tempNote;
  state.pendingPublic = tempNote.is_public;
  updateNoteMetaDisplay(state.current);
  cacheNote(tempNote).catch(() => {});
  await queueOutbox('create', { ...payload, tempId });
  state.dirty = false;
  updateSaveButtonState();
  updateStatus('Queued');
  showToast('Queued for sync', 'info');
  flashSaveSuccess();
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
  updateSaveButtonState();
  updateCollections(state.current);
  updateNoteMetaDisplay(state.current);
  updateStatus('Queued');
  showToast('Queued for sync', 'info');
  flashSaveSuccess();
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
  const normalizedId = normalizeNoteId(note.id ?? note.note_id ?? note.noteId ?? null);
  return {
    id: normalizedId,
    slug: note.slug || '',
    title: note.title || 'Untitled',
    tags: Array.isArray(note.tags) ? note.tags : (note.tags ? note.tags.split(',').map((t) => t.trim()).filter(Boolean) : []),
    content: note.content || '',
    updated_at: note.updated_at,
    is_public: Boolean(note.is_public),
    version: note.version || 1,
    temp: Boolean(note.temp) || isTempId(normalizedId),
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
    node.dataset.slug = note.slug || '';
    if (hasValidId(note.id)) {
      node.dataset.id = String(note.id);
    } else {
      delete node.dataset.id;
    }
    node.querySelector('.note-item__title').textContent = note.title || 'Untitled';
    node.querySelector('.note-item__meta').textContent = formatMeta(note);
    const previewEl = node.querySelector('.note-item__preview');
    if (previewEl) {
      const snippetSource = note.preview || note.content || '';
      const normalized = snippetSource.trim().replace(/\s+/g, ' ');
      previewEl.textContent = normalized ? normalized.slice(0, 120) : 'No additional details yet.';
    }
    node.addEventListener('click', () => openNoteFromMeta(note).catch(() => {}));
    elements.noteList.appendChild(node);
    if (window.gsap) {
      gsap.from(node, { opacity: 0, x: -12, duration: 0.26, ease: 'power2.out' });
    }
  });
  highlightActiveNote(state.current?.id, state.current?.slug);
  if (elements.notesCount) {
    const count = state.notes.length;
    elements.notesCount.textContent = `${count} ${count === 1 ? 'item' : 'items'}`;
  }
}

function renderTags() {
  if (!elements.tagList) return;
  elements.tagList.innerHTML = '';
  state.tags.forEach((tag) => {
    const btn = document.createElement('button');
    btn.textContent = `#${tag}`;
    btn.className = 'pill-muted';
    if (state.filterTag === tag) {
      btn.classList.add('pill-active');
    }
    btn.addEventListener('click', () => {
      state.filterTag = state.filterTag === tag ? null : tag;
      applyFilters();
      renderTags();
    });
    elements.tagList.appendChild(btn);
  });
  if (elements.tagsCount) {
    const total = state.tags.length;
    elements.tagsCount.textContent = `${total} ${total === 1 ? 'tag' : 'tags'}`;
  }
}

function collectTags(notes) {
  const set = new Set();
  notes.forEach((note) => {
    (note.tags || []).forEach((tag) => set.add(tag));
  });
  return Array.from(set).sort();
}

function filterByMode(notes, mode) {
  const target = (mode || 'all').toLowerCase();
  switch (target) {
    case 'favorites':
      return notes.filter((note) => (note.tags || []).some((tag) => tag.toLowerCase() === 'favorite'));
    case 'public':
      return notes.filter((note) => Boolean(note.is_public));
    case 'drafts':
      return notes.filter((note) => !note.is_public);
    default:
      return notes;
  }
}

function updateFilterButtons() {
  if (!elements.filterButtons) {
    return;
  }
  elements.filterButtons.forEach((button) => {
    const current = (button.dataset.filter || 'all').toLowerCase();
    const isActive = current === state.filterMode;
    button.classList.toggle('pill-active', isActive);
    button.classList.toggle('pill-muted', !isActive);
    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
  });
}

function setFilterMode(mode) {
  const normalized = (mode || 'all').toLowerCase();
  const allowed = ['all', 'favorites', 'public', 'drafts'];
  if (!allowed.includes(normalized)) {
    return;
  }
  if (state.filterMode === normalized) {
    updateFilterButtons();
    return;
  }
  state.filterMode = normalized;
  updateFilterButtons();
  applyFilters();
}

function applyFilters() {
  let filtered = [...state.allNotes];
  if (state.filterMode && state.filterMode !== 'all') {
    filtered = filterByMode(filtered, state.filterMode);
  }
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

function highlightActiveNote(id, slug) {
  document.querySelectorAll('.note-item').forEach((el) => {
    const matchesId = id && String(el.dataset.id) === String(id);
    const matchesSlug = !id && slug && el.dataset.slug === slug;
    const isActive = Boolean(matchesId || matchesSlug);
    el.classList.toggle('active', isActive);
    el.setAttribute('aria-current', isActive ? 'true' : 'false');
  });
}

function handleSearch(event) {
  state.searchTerm = event.target.value;
  toggleSearchClear(state.searchTerm);
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
  toggleSearchClear('');
  applyFilters();
}

function toggleSearchClear(value) {
  if (!elements.searchClear) {
    return;
  }
  const visible = Boolean(value && value.trim().length > 0);
  elements.searchClear.classList.toggle('hidden', !visible);
}

function handleQuickAction(action) {
  if (!action) {
    return;
  }
  const intent = action.toLowerCase();
  switch (intent) {
    case 'save':
      if (elements.saveBtn?.disabled) {
        return;
      }
      try {
        elements.saveBtn?.focus({ preventScroll: true });
      } catch (err) {
        elements.saveBtn?.focus();
      }
      elements.saveBtn?.click();
      break;
    case 'visibility':
      elements.shareBtn?.click();
      break;
    case 'delete':
      deleteCurrentNote();
      break;
    case 'new':
      createNewNote();
      break;
    case 'theme':
      toggleTheme();
      break;
    default:
      break;
  }
}

function handleUndo() {
  focusEditor();
  if (typeof undoEditor === 'function') {
    undoEditor();
  } else if (elements.editorTextarea) {
    elements.editorTextarea.focus();
    try {
      document.execCommand('undo');
    } catch (err) {
      // ignore unsupported command
    }
  }
}

function handleRedo() {
  focusEditor();
  if (typeof redoEditor === 'function') {
    redoEditor();
  } else if (elements.editorTextarea) {
    elements.editorTextarea.focus();
    try {
      document.execCommand('redo');
    } catch (err) {
      // ignore unsupported command
    }
  }
}

function getMetadataState() {
  if (!elements.metaDetails) {
    return false;
  }
  return elements.metaDetails.getAttribute('data-open') !== 'false';
}

function setMetadataState(open) {
  if (!elements.metaDetails) {
    return;
  }
  const next = open ? 'true' : 'false';
  elements.metaDetails.setAttribute('data-open', next);
  const fields = elements.metaDetails.querySelector('.metadata-fields');
  const toggle = elements.metaDetails.querySelector('button');
  const caret = elements.metaDetails.querySelector('#metadataCaret');
  toggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
  elements.settingsBtn?.setAttribute('aria-pressed', open ? 'true' : 'false');
  if (caret) {
    caret.classList.toggle('rotate-180', open);
  }
  animateMetadata(open, fields);
}

function toggleMetadataPanel(force) {
  if (!elements.metaDetails) {
    return;
  }
  const next = typeof force === 'boolean' ? force : !getMetadataState();
  setMetadataState(next);
}

function animateMetadata(open, fields) {
  const target = fields || elements.metaDetails?.querySelector('.metadata-fields');
  if (!target) {
    return;
  }
  if (!window.gsap) {
    target.classList.toggle('hidden', !open);
    return;
  }
  if (open) {
    target.classList.remove('hidden');
    gsap.fromTo(target, { opacity: 0, y: -8 }, { opacity: 1, y: 0, duration: 0.24, ease: 'power2.out' });
  } else {
    gsap.to(target, {
      opacity: 0,
      y: -6,
      duration: 0.2,
      ease: 'power1.in',
      onComplete: () => target.classList.add('hidden'),
    });
  }
}

function setBrandPulse(active) {
  if (!elements.brandPulse) {
    return;
  }
  elements.brandPulse.classList.toggle('is-active', active);
  if (!window.gsap) {
    return;
  }
  if (active) {
    if (brandPulseTween) {
      return;
    }
    brandPulseTween = gsap
      .timeline({ repeat: -1, defaults: { duration: 0.9, ease: 'sine.inOut' } })
      .to(elements.brandPulse, { scale: 1.15, opacity: 0.9 })
      .to(elements.brandPulse, { scale: 0.85, opacity: 0.35 });
  } else if (brandPulseTween) {
    brandPulseTween.kill();
    brandPulseTween = null;
    gsap.set(elements.brandPulse, { scale: 1, opacity: 0.4 });
  }
}

function setFooterPulse(active) {
  if (!elements.footerPulse) {
    return;
  }
  elements.footerPulse.classList.toggle('is-active', active);
}

function setVisibilityState(makePublic) {
  if (!elements.notePublic) {
    return;
  }
  const desired = Boolean(makePublic);
  if (elements.notePublic.checked === desired) {
    syncVisibilityControls();
    return;
  }
  elements.notePublic.checked = desired;
  handlePublishToggle();
}

function handlePublishToggle() {
  if (!state.current) return;
  const makePublic = elements.notePublic.checked;
  state.current.is_public = makePublic;
  state.pendingPublic = makePublic;

  syncVisibilityControls();

  if (!state.current.id || state.current.temp) {
    updateStatus('Queued');
    showToast(makePublic ? 'Will publish after save' : 'Will keep private until saved', 'info');
    return;
  }

  if (state.offline) {
    queuePublish(state.current, makePublic);
    state.current.updated_at = new Date().toISOString();
    updateCollections(state.current);
    updateNoteMetaDisplay(state.current);
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
      syncVisibilityControls();
      updateNoteMetaDisplay(state.current);
    })
    .catch((err) => {
      elements.notePublic.checked = !makePublic;
      state.current.is_public = !makePublic;
      showToast('Publish failed', 'error');
      console.error(err);
      syncVisibilityControls();
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
          if (created) {
            cacheNote(created).catch(() => {});
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
          cacheNote(updated).catch(() => {});
          break;
        }
        case 'delete': {
          await deleteNote(entry.payload.id);
          removeFromCollections(entry.payload.id);
          removeCachedNote(entry.payload.id).catch(() => {});
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
    if (state.current) {
      await openNoteFromMeta(state.current, { silent: true });
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
  removeCachedNote(tempId).catch(() => {});
  updateCollections(created);
  cacheNote(created).catch(() => {});
  applyNote(created);
}

function syncVisibilityControls() {
  const shareActive = Boolean(elements.notePublic?.checked);
  if (elements.shareBtn) {
    elements.shareBtn.classList.toggle('is-public', shareActive);
    elements.shareBtn.setAttribute('aria-pressed', shareActive ? 'true' : 'false');
    elements.shareBtn.dataset.state = shareActive ? 'public' : 'private';
  }
  if (elements.visibilityPrivate && elements.visibilityPublic) {
    elements.visibilityPrivate.classList.toggle('pill-active', !shareActive);
    elements.visibilityPrivate.classList.toggle('pill-muted', shareActive);
    elements.visibilityPrivate.setAttribute('aria-pressed', shareActive ? 'false' : 'true');
    elements.visibilityPublic.classList.toggle('pill-active', shareActive);
    elements.visibilityPublic.classList.toggle('pill-muted', !shareActive);
    elements.visibilityPublic.setAttribute('aria-pressed', shareActive ? 'true' : 'false');
  }
}

function toggleFocusMode(force) {
  const next = typeof force === 'boolean' ? force : !state.focusMode;
  state.focusMode = next;
  document.body.classList.toggle('is-focus-mode', next);
  if (elements.focusToggle) {
    elements.focusToggle.setAttribute('aria-pressed', next ? 'true' : 'false');
  }
  if (next) {
    toggleSidebar(false);
    setMetadataState(false);
  }
}

function toggleSidebar(force) {
  if (!elements.sidebar) return;
  if (state.focusMode && force !== false) {
    return;
  }
  const open = typeof force === 'boolean' ? force : !elements.sidebar.classList.contains('is-open');
  elements.sidebar.classList.toggle('is-open', open);
  if (open) {
    elements.sidebar.classList.remove('-translate-x-full');
    if (elements.sidebarBackdrop) {
      elements.sidebarBackdrop.classList.remove('hidden');
      void elements.sidebarBackdrop.offsetWidth;
      elements.sidebarBackdrop.classList.add('is-visible');
    }
  } else {
    if (window.innerWidth < 1024) {
      elements.sidebar.classList.add('-translate-x-full');
    }
    if (elements.sidebarBackdrop) {
      elements.sidebarBackdrop.classList.remove('is-visible');
      window.setTimeout(() => {
        elements.sidebarBackdrop?.classList.add('hidden');
      }, 180);
    }
  }
  document.body.classList.toggle('sidebar-open', open);
  if (elements.sidebarToggle) {
    elements.sidebarToggle.setAttribute('aria-expanded', String(open));
  }
}

function getStoredTheme() {
  try {
    return window.localStorage ? localStorage.getItem(THEME_KEY) : null;
  } catch (err) {
    return null;
  }
}

function setStoredTheme(value) {
  try {
    if (window.localStorage) {
      localStorage.setItem(THEME_KEY, value);
    }
  } catch (err) {
    console.warn('Theme preference could not be stored', err);
  }
}

function toggleTheme() {
  const root = document.documentElement;
  const current = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  const next = current === 'dark' ? 'light' : 'dark';
  root.setAttribute('data-theme', next);
  root.classList.toggle('dark', next === 'dark');
  document.body.dataset.theme = next;
  document.body.classList.toggle('dark', next === 'dark');
  document.cookie = `${THEME_KEY}=${next}; path=/; max-age=31536000`;
  setStoredTheme(next);
  if (elements.themeToggleIcon) {
    elements.themeToggleIcon.classList.remove('ph-sun-dim', 'ph-moon-stars');
    elements.themeToggleIcon.classList.add(next === 'dark' ? 'ph-moon-stars' : 'ph-sun-dim');
  }
  window.dispatchEvent(new CustomEvent('markly-theme', { detail: next }));
  triggerThemeTransition();
}

function handleLogout() {
  logout()
    .then(() => {
      location.href = '/login.php';
    })
    .catch(() => {
      location.href = '/logout.php';
    });
}

function showToast(message, type = 'info', options = {}) {
  if (!elements.toastContainer) {
    return { dismiss() {} };
  }

  const toast = document.createElement('div');
  toast.className = 'toast-card';
  toast.classList.add(`toast-card--${type}`);
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

function copyCurrentLink() {
  if (!state.current || !state.current.slug) {
    showToast('Add a slug to copy a link', 'info');
    return;
  }
  const origin = window.location.origin.replace(/\/$/, '');
  const link = `${origin}/${state.current.slug}`;
  if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
    navigator.clipboard
      .writeText(link)
      .then(() => showToast('Link copied to clipboard', 'success'))
      .catch(() => fallbackCopy(link));
  } else {
    fallbackCopy(link);
  }
}

function fallbackCopy(value) {
  try {
    const tempInput = document.createElement('input');
    tempInput.value = value;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand('copy');
    tempInput.remove();
    showToast('Link copied to clipboard', 'success');
  } catch (err) {
    showToast('Unable to copy link', 'error');
  }
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
      .register('/sw.js')
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
  fetch('/version.json', { cache: 'no-store' })
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
      elements.backlinks.innerHTML = '<p class="rounded-2xl border border-dashed border-border px-4 py-4 text-center text-xs text-slate-400 dark:border-white/10 dark:text-slate-500">No backlinks yet. Mention this note elsewhere to build context.</p>';
      return;
    }
    const fragment = document.createDocumentFragment();
    links.forEach((link) => {
      const anchor = document.createElement('a');
      anchor.className = 'flex items-start gap-3 rounded-2xl border border-transparent bg-white/70 px-4 py-3 transition hover:-translate-y-0.5 hover:border-accent/40 hover:shadow-soft dark:bg-white/10';
      anchor.href = link.href || '#';
      anchor.target = '_self';
      anchor.rel = 'noopener';
      anchor.innerHTML = `
        <span class="mt-1 text-base text-accent"><i class="ph ph-caret-right"></i></span>
        <div>
          <p class="text-sm font-semibold text-slate-900 dark:text-white">${escapeHtml(link.title || link.slug || 'Related note')}</p>
          <p class="text-xs text-slate-500 dark:text-slate-300">${escapeHtml(link.preview || '')}</p>
        </div>
      `;
      fragment.appendChild(anchor);
    });
    elements.backlinks.innerHTML = '';
    elements.backlinks.appendChild(fragment);
  } catch (err) {
    console.error('Backlinks failed', err);
  }
}

window.MarklyApp = {
  syncOutbox,
};
