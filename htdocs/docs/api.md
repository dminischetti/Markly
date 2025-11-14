# Markly API Documentation

All API endpoints return JSON. Authentication is handled via PHP sessions with CSRF tokens.

## Authentication

### POST `/api/auth.php?action=login`
Login with email and password.

**Request**:
```json
{
  "_token": "csrf_token_here",
  "email": "user@example.com",
  "password": "password123"
}
```

**Response** (200):
```json
{
  "ok": true,
  "email": "user@example.com",
  "csrf": "new_csrf_token"
}
```

**Errors**:
- 401: Invalid credentials
- 422: Missing email or password

### POST `/api/auth.php?action=logout`
Logout current user.

**Request**:
```json
{
  "_token": "csrf_token_here"
}
```

**Response** (200):
```json
{
  "ok": true
}
```

### GET `/api/auth.php?action=session`
Check current session status.

**Response** (200):
```json
{
  "auth": true,
  "email": "user@example.com"
}
```

### GET `/api/auth.php?action=csrf`
Get a fresh CSRF token.

**Response** (200):
```json
{
  "csrf": "new_csrf_token"
}
```

## Notes

### GET `/api/notes.php`
List all notes for authenticated user.

**Response** (200):
```json
{
  "notes": [
    {
      "id": 1,
      "slug": "welcome-to-markly",
      "title": "Welcome to Markly",
      "tags": ["intro", "getting-started"],
      "is_public": true,
      "version": 1,
      "updated_at": "2025-01-15 10:30:00"
    }
  ],
  "tags": ["intro", "getting-started", "planning"]
}
```

### GET `/api/notes.php?id={id}`
Get a specific note by ID (requires authentication).

**Response** (200):
```json
{
  "note": {
    "id": 1,
    "slug": "welcome-to-markly",
    "title": "Welcome to Markly",
    "content": "# Welcome\n\nMarkdown content here...",
    "tags": ["intro"],
    "tags_raw": "intro,getting-started",
    "is_public": true,
    "version": 1,
    "updated_at": "2025-01-15 10:30:00",
    "created_at": "2025-01-10 09:00:00"
  },
  "etag": "\"v1\""
}
```

**Headers**:
- `If-None-Match`: Send ETag to check if note changed (returns 304 if unchanged)

**Errors**:
- 404: Note not found
- 401: Unauthorized

### GET `/api/notes.php?slug={slug}`
Get a note by slug. Returns public notes for unauthenticated users.

**Response**: Same as GET by ID

### GET `/api/notes.php?search={term}`
Search notes by title and content.

**Response** (200):
```json
{
  "results": [
    {
      "id": 1,
      "slug": "welcome",
      "title": "Welcome to Markly",
      "tags": ["intro"],
      "is_public": true,
      "version": 1,
      "updated_at": "2025-01-15 10:30:00"
    }
  ]
}
```

### GET `/api/notes.php?tag={tag}`
Filter notes by tag.

**Response**: Same as search

### GET `/api/notes.php?backlinks={slug}`
Get notes that link to a specific note.

**Response** (200):
```json
{
  "results": [
    {
      "id": 2,
      "slug": "related-note",
      "title": "Related Note"
    }
  ]
}
```

### POST `/api/notes.php`
Create a new note.

**Request**:
```json
{
  "_token": "csrf_token",
  "title": "My New Note",
  "content": "# Content\n\nMarkdown here...",
  "tags": "tag1,tag2",
  "slug": "my-new-note"
}
```

**Response** (201):
```json
{
  "note": { /* full note object */ }
}
```

**Errors**:
- 422: Validation error (missing title/content)

### POST `/api/notes.php` (with `_method=PUT`)
Update an existing note.

**Request**:
```json
{
  "_method": "PUT",
  "_token": "csrf_token",
  "id": 1,
  "version": 1,
  "title": "Updated Title",
  "content": "Updated content...",
  "tags": "tag1,tag2",
  "slug": "updated-slug"
}
```

**Headers**:
- `If-Match`: `"v1"` (current version for optimistic locking)

**Response** (200):
```json
{
  "note": { /* updated note with version incremented */ }
}
```

**Errors**:
- 409: Version conflict (note was modified by another request)
- 404: Note not found
- 428: Missing version/If-Match header

### POST `/api/notes.php` (with `_method=DELETE`)
Delete a note.

**Request**:
```json
{
  "_method": "DELETE",
  "_token": "csrf_token",
  "id": 1
}
```

**Response** (200):
```json
{
  "ok": true
}
```

## Publish

### POST `/api/publish.php`
Toggle public visibility for a note.

**Request**:
```json
{
  "_token": "csrf_token",
  "id": 1,
  "public": true
}
```

**Response** (200):
```json
{
  "ok": true,
  "is_public": true
}
```

**Errors**:
- 404: Note not found

## Error Response Format

All errors follow this structure:
```json
{
  "error": "error_code",
  "message": "Human-readable error message (optional)"
}
```

Common error codes:
- `invalid_csrf`: CSRF token validation failed
- `unauthorised`: Authentication required
- `not_found`: Resource not found
- `validation_failed`: Input validation failed
- `version_conflict`: Optimistic locking conflict

## Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `invalid_credentials` | 401 | Email or password is incorrect |
| `unauthorised` | 401 | Authentication required |
| `invalid_csrf` | 400 | CSRF token is invalid or expired |
| `not_found` | 404 | Requested resource doesn't exist |
| `validation_failed` | 422 | Input validation failed |
| `version_conflict` | 409 | Optimistic locking conflict detected |
| `missing_if_match` | 428 | Version header required for updates |
| `unknown_action` | 400 | Invalid action parameter |
| `unsupported_method` | 405 | HTTP method not allowed |
