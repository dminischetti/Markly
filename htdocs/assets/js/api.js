const state = {
  routes: {},
  csrf: null,
  etags: new Map(),
  csrfPromise: null,
};

class ApiError extends Error {
  constructor(status, body) {
    super(body && body.error ? body.error : `Request failed with status ${status}`);
    this.status = status;
    this.body = body;
  }
}

export function initApi(routes, csrfToken) {
  state.routes = routes;
  state.csrf = csrfToken || null;
}

export function getRoutes() {
  return state.routes;
}

export async function refreshCsrf() {
  if (state.csrfPromise) {
    return state.csrfPromise;
  }
  state.csrfPromise = fetch(state.routes.auth + '?action=csrf', {
    credentials: 'same-origin',
  })
    .then((res) => res.json())
    .then((data) => {
      state.csrf = data.csrf;
      state.csrfPromise = null;
      return state.csrf;
    })
    .catch((err) => {
      state.csrfPromise = null;
      throw err;
    });
  return state.csrfPromise;
}

async function ensureCsrf() {
  if (!state.csrf) {
    await refreshCsrf();
  }
  return state.csrf;
}

async function apiGet(url, params = {}, options = {}) {
  const search = new URLSearchParams(params);
  const res = await fetch(url + (search.toString() ? `?${search.toString()}` : ''), {
    method: 'GET',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      ...(options.ifNoneMatch ? { 'If-None-Match': options.ifNoneMatch } : {}),
    },
  });

  if (res.status === 304) {
    return { status: 304 };
  }

  const body = await res.json().catch(() => ({}));
  if (!res.ok) {
    throw new ApiError(res.status, body);
  }
  return { status: res.status, body, headers: res.headers };
}

async function apiPost(url, payload = {}, options = {}) {
  await ensureCsrf();
  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-CSRF-Token': state.csrf,
  };

  if (options.ifMatch) {
    headers['If-Match'] = options.ifMatch;
  }

  const res = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers,
    body: JSON.stringify(payload),
  });

  const body = await res.json().catch(() => ({}));

  if (body && body.csrf) {
    state.csrf = body.csrf;
  }

  try {
    await refreshCsrf();
  } catch (err) {
    console.error('Failed to refresh CSRF token', err);
  }

  if (!res.ok) {
    throw new ApiError(res.status, body);
  }

  return { status: res.status, body, headers: res.headers };
}

export async function loadAllNotes() {
  const { body, headers } = await apiGet(state.routes.notes);
  const etag = headers?.get('ETag');
  if (etag) {
    state.etags.set('notes:list', etag);
  }
  return body;
}

export async function getNoteById(id, options = {}) {
  const etagKey = `note:${id}`;
  const ifNone = options.useCache ? state.etags.get(etagKey) : null;
  const { status, body, headers } = await apiGet(state.routes.notes, { id }, { ifNoneMatch: ifNone });
  if (status === 304) {
    return { cached: true };
  }
  const etag = headers?.get('ETag');
  if (etag) {
    state.etags.set(etagKey, etag);
  }
  return body;
}

export async function getNoteBySlug(slug, options = {}) {
  const etagKey = `slug:${slug}`;
  const ifNone = options.useCache ? state.etags.get(etagKey) : null;
  const { status, body, headers } = await apiGet(state.routes.notes, { slug }, { ifNoneMatch: ifNone });
  if (status === 304) {
    return { cached: true };
  }
  const etag = headers?.get('ETag');
  if (etag) {
    state.etags.set(etagKey, etag);
  }
  return body;
}

export async function searchNotes(term) {
  if (!term || !term.trim()) {
    return { results: [] };
  }
  return apiGet(state.routes.notes, { search: term.trim() });
}

export async function listByTag(tag) {
  return apiGet(state.routes.notes, { tag });
}

export async function createNote(payload) {
  const { body, headers } = await apiPost(state.routes.notes, {
    title: payload.title,
    content: payload.content,
    tags: payload.tags,
    slug: payload.slug,
  });
  const note = body.note;
  if (note?.id) {
    state.etags.set(`note:${note.id}`, headers?.get('ETag'));
  }
  return note;
}

export async function updateNote(note) {
  const { body, headers } = await apiPost(state.routes.notes, {
    _method: 'PUT',
    id: note.id,
    title: note.title,
    content: note.content,
    tags: note.tags,
    slug: note.slug,
  }, {
    ifMatch: `"v${note.version}"`,
  });
  const updated = body.note;
  if (updated?.id) {
    state.etags.set(`note:${updated.id}`, headers?.get('ETag'));
  }
  return updated;
}

export async function deleteNote(id) {
  await apiPost(state.routes.notes, { _method: 'DELETE', id });
  state.etags.delete(`note:${id}`);
}

export async function togglePublish(id, isPublic) {
  const { body } = await apiPost(state.routes.publish, { id, public: isPublic ? 1 : 0 });
  return body;
}

export async function logout() {
  await apiPost(state.routes.auth + '?action=logout', {});
}

export async function getBacklinks(slug) {
  const { body } = await apiGet(state.routes.notes, { backlinks: slug });
  return body.results || [];
}

export async function getGraph() {
  const { body } = await apiGet(state.routes.notes, { graph: 1 });
  return body;
}

export { ApiError };
