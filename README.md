# Markly - Offline-First Markdown Knowledge Hub

**Stack:** PHP 8 • MySQL • Vanilla JS • IndexedDB • Service Worker • OKLCH Design Tokens
**Live Demo:** https://lab.minischetti.org/markly/

> A login-protected, offline-capable Markdown notebook powered by pure PHP and the web platform.
> No frameworks. No build pipeline. Deployable anywhere - shared hosting, VPS, or containers.

---

## ⚡ Quick Facts

- **Project type:** Offline-first Markdown workspace with full CRUD, tagging, search, backlinks, and public note sharing
- **Frontend:** Vanilla JS modules with live preview, metadata panel, keyboard shortcuts
- **Data layer:** IndexedDB (offline cache + outbox queue)
- **Backend:** PHP 8, PDO, secure sessions, CSRF, optimistic locking
- **Deployment:** Drop-in `/htdocs` structure; works from FTP hosting to Kubernetes
- **Design:** OKLCH colors, responsive split views, smooth micro-interactions

---

## 📊 Project Metrics

- **Lines of code:** ~2.8k
- **Dependencies:** None (optional CDN: Marked.js + DOMPurify)
- **DB tables:** `users`, `notes`
- **APIs:** `/api/notes.php`, `/api/auth.php`, `/api/publish.php`
- **Storage:** IndexedDB for offline notes + request outbox
- **Search:** MySQL FULLTEXT with LIKE fallback
- **Tests:** PHPUnit for repositories, utilities, and CSRF lifecycle

---

## 🎯 Technical Highlights

### Offline-First Architecture
- Service worker precaches the shell
- IndexedDB stores notes, tags, and queued mutations
- On reconnect: queued requests replay with optimistic locking + conflict detection
- Status pills: *Queued → Saving → Saved*

### Optimistic Locking
- Notes carry a `version` field
- Client sends `If-Match` ETag
- API only updates when versions match
- Conflicts return `409 Conflict` with server copy for merge flow

### Security Model
- Session hardening + SameSite cookies
- CSRF rotation across login/logout
- Prepared statements everywhere
- DOMPurify for preview sanitization
- Public pages fully escaped with `htmlspecialchars`

### Zero Build Tools
- Pure ES modules
- Static markup + API endpoints
- Works on shared hosting with no Node/Composer

---

## ✨ Feature Highlights

- 🔐 **Solid Authentication**
  Email/password login, CSRF, ID regeneration, hardened sessions.

- 📝 **Markdown Workspace**
  Live preview, tags, backlinks, autosave, keyboard shortcuts, metadata drawer.

- 🔍 **Smart Organisation**
  FULLTEXT search, tag filtering, backlinks discovery.

- 📶 **Offline-Ready**
  Shell cache + IndexedDB cache + request outbox + graceful sync toasts.

- 🌗 **Beautiful UI**
  OKLCH-based light/dark themes, polished transitions, friendly on both mobile and desktop.

- ☁️ **Deploy Anywhere**
  No Composer, no build tools - just PHP and MySQL.

---

## 🔒 Security Practices

- CSRF tokens on all state-changing requests
- Secure session cookies (HttpOnly + SameSite)
- Session ID regeneration
- DOMPurify for safe HTML preview
- Prepared statements with PDO
- Public note view fully sanitized

---

## 🧩 Architecture Overview

| Layer | Details |
|-------|---------|
| Backend | Pure PHP 8, PDO, no frameworks |
| Database | MySQL (utf8mb4), FULLTEXT index on title/body |
| Domain | `/src` classes: `Auth`, `NotesRepo`, `LinksRepo`, `Csrf`, etc. |
| SPA | Vanilla JS: `app.js`, `api.js`, `editor.js`, `db.js` |
| Offline | Service worker + IndexedDB caches + outbox queue |

---

## 💡 Technical Challenges Solved

### 1. Offline Sync + Conflict Handling
Replayed changes with version checks and graceful recovery using ETags and 409 responses.

### 2. IndexedDB Outbox Deduplication
Queued operations deduplicated by type, note ID, and payload hash.

### 3. CSRF Token Rotation
Tokens rotate on login/logout, with the API holding a short pool to allow concurrent requests.

### 4. Shared Hosting Constraints
All functionality implemented without Composer, Node, or frameworks.

### 5. Service Worker Scoping
Proper SW scope for installs in `/htdocs` or subdirectories - avoids cache poisoning and routing edge cases.

---

## 🧠 How It Works (Short Version)

### Backend
- `index.php` boots sessions and loads the SPA.
- `/api/*.php` endpoints handle CRUD, tags, backlinks, and publish toggles.
- Strict JSON output, early exits, no body rendering.

### Frontend
- JS modules control sidebar, editor, sync state, keyboard shortcuts, and live preview.
- ETags from server map to optimistic locking version numbers.

### Offline
- IndexedDB stores notes + pending changes.
- On reload: notes come from DB immediately; API refreshes them when online.
- Outbox replays sequentially with conflict resolution.

---

## 📚 What I Learned

1. Service worker lifecycle, scoping, and cache versioning
2. IndexedDB patterns: object stores, cursors, atomic transactions
3. Practical optimistic UI design
4. Secure PHP session and CSRF handling
5. PWA considerations (manifest, icons, offline shell)
6. Designing a cohesive visual system with OKLCH

---

## 🚀 Getting Started

### Requirements
- PHP ≥ 8.0
- MySQL 5.7+ / MariaDB 10.3+
- Apache or Nginx
- HTTPS (required for service worker)

### Installation

1. **Clone the repository**
```bash
git clone https://github.com/yourusername/markly.git
cd markly
```

2. **Create the database**
```sql

CREATE DATABASE markly CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
3. **Import schema (and seed)**
```bash
mysql -u <user> -p markly < htdocs/sql/schema.sql
mysql -u <user> -p markly < htdocs/sql/seed.sql
```

4. Configure the app
```bash
cp htdocs/config/config.example.php htdocs/config/config.php
```

Set up credentials
```php
'db' => [
    'dsn'  => 'mysql:host=localhost;dbname=markly;charset=utf8mb4',
    'user' => 'your_db_user',
    'pass' => 'your_db_password',
],
```

5. **Deploy to web server**
   - Upload `htdocs/` contents to your web root
   - Ensure `.htaccess` is present (Apache) or configure equivalent for Nginx
   - Set proper file permissions (755 for directories, 644 for files)

6. **Access the application**
   - Navigate to your domain: `https://yourdomain.com/`
   - Login with demo credentials: `admin@example.com` / `admin123`

### Local Development

Using PHP's built-in server:
```bash
cd htdocs
php -S localhost:8000
```

Visit `http://localhost:8000/login.php`

**Note**: Service Worker requires HTTPS in production. For local development, localhost is exempt.

---

## 🔁 Deployment

### Shared Hosting (cPanel, Plesk, etc.)
- Upload the `htdocs/` directory to your hosting account's web root via FTP or file manager.
- Create the database using the hosting control panel and import `schema.sql` + `seed.sql`.
- Configure environment variables or `config.php` with production credentials.
- Confirm `.htaccess` rules are active for clean URLs, service worker scope, and manifest MIME types.

### Virtual Private Server (VPS)
- Provision PHP 8, MySQL, and a web server (Apache or Nginx + PHP-FPM).
- Clone the repository or deploy via CI/CD.
- Configure vhost to serve `htdocs/` as the document root and enable HTTPS (Let's Encrypt recommended).
- Set environment variables in your process manager (systemd, supervisord) or web server config.
- Harden permissions: non-root user, `chmod` 644/755, and restrict write access to necessary directories.

### Docker (Optional)
- Use the provided PHP/Apache or PHP-FPM base image.
- Copy `htdocs/` into the container and mount persistent storage for logs/uploads if required.
- Run migrations via `mysql` client container or use docker-compose service for MySQL.
- Expose port 80/443 through your orchestrator and configure HTTPS termination.

---

## Demo credentials

- Email: `admin@example.com`
- Password: `admin123`

Feel free to change the password or add additional users directly in the `users` table.

## Offline & sync workflow

- Notes opened while online are cached in IndexedDB for quick access offline.
- Edits while offline queue into the outbox; when connectivity returns, creates/updates/deletes/publish toggles replay sequentially with conflict detection.
- Toasts announce `Syncing…` and `Synced` states, while the status pill reflects `Queued`, `Saving…`, or `Saved`.
- The service worker precaches the shell and keeps IndexedDB + the API in sync using a network-first strategy with cache fallback.

## ✅ QA Checklist

- [ ] Database created and both `htdocs/sql/schema.sql` + `htdocs/sql/seed.sql` imported.
- [ ] `htdocs/config/config.php` updated with live credentials.
- [ ] `/htdocs` (including `.htaccess`, `manifest.webmanifest`, and `sw.js`) uploaded to the server root.
- [ ] Service worker registered successfully (check browser DevTools → Application → Service Workers).
- [ ] Login with the demo account works and sessions persist across reloads.
- [ ] Create, edit, delete, and publish notes online and offline; queued changes sync after reconnecting.
- [ ] Responsive layout verified on desktop (split view), tablet (sticky tabs), and mobile (drawer sidebar, full-width editor).
- [ ] Public permalink (`/index.php?p=<slug>`) renders the note for unauthenticated visitors.

---

## 🧪 Running Tests
```bash
# Install PHPUnit (if not already installed)
composer require --dev phpunit/phpunit ^10.0

# Run test suite
./vendor/bin/phpunit
```

**Test Coverage**: Core domain logic (NotesRepo, TextUtil, Csrf) covered with fast in-memory databases.

## License

MIT License - see LICENSE.
