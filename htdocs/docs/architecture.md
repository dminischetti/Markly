# Markly Architecture

## Overview
Markly deploys as a traditional PHP application where `/htdocs/index.php` is both the routing shell and the SPA bootstrapper. Sessions are initialised through `Markly\Auth`, which honours production hosting cookie constraints and injects CSRF tokens plus runtime constants (version, storage prefixes) into the page. From there, the vanilla JS modules mount onto the Markly editor component and control the note workspace without any build tooling.

## Request flow
```mermaid
flowchart LR
  User[User] -->|Login, actions| UI[Vanilla JS SPA]
  UI -->|Fetch/POST| API[/api/*.php]
  API -->|CRUD| NotesRepo[(MySQL notes table)]
  API -->|Auth| AuthRepo[(users table)]
  UI -->|Cache| IDB[(IndexedDB mdpro_* stores)]
  UI -->|Sync| SW[(Service Worker v1.2.0)]
  SW -->|Network-first| API
```

## Layers
**Backend** – Domain services in `/htdocs/src/` (Auth, NotesRepo, LinksRepo, Csrf, Response, TextUtil) encapsulate persistence and security concerns. APIs are thin scripts that decode payloads, validate CSRF tokens, and delegate to repositories. All SQL uses prepared statements with optimistic locking and ETag headers to prevent lost updates.

**Frontend** – `htdocs/assets/js/app.js` orchestrates state, keyboard shortcuts, drawer UI, and toast notifications. `api.js` owns fetch calls, CSRF refresh, and ETag memoisation. `db.js` namespaces IndexedDB stores with `mdpro_` prefixes, deduplicates queued mutations, and exposes listeners for UI badges. `editor.js` binds toolbar actions to the existing Markly textarea/preview component, while `graph.js` can display backlinks when data is available.

**Offline + delivery** – `sw.js` (versioned `v1.2.0`) precaches the shell, upgrades caches on activate, and logs its status for debugging. Pending mutations queue into the IndexedDB outbox and replay when the browser fires an `online` event. `.htaccess` ensures `markly/sw.js` and `markly/manifest.webmanifest` are served with the correct MIME types so the service worker can claim the `/markly/` scope on production hosting.
