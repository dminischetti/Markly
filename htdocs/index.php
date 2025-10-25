<?php
declare(strict_types=1);

require __DIR__ . '/config/database.php';
require __DIR__ . '/src/autoload.php';

use dminischetti\Markly\Markly;
use Markly\Auth;
use Markly\Constants;
use Markly\Csrf;
use Markly\NotesRepo;

$config = markly_config();
$pdo = markly_pdo();
$auth = new Auth($pdo, $config);
$repo = new NotesRepo($pdo);

$slug = isset($_GET['p']) ? (string)$_GET['p'] : null;

if ($slug !== null) {
    $note = $repo->findPublicBySlug($slug);
    $title = $note ? ($note['title'] !== '' ? (string)$note['title'] : 'Untitled note') : 'Note not found';
    $description = $note ? substr(strip_tags($note['content']), 0, 160) : 'This note is no longer available.';
    ?><!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($title . ' · ' . Constants::APP_NAME, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#f9fafb">
    <link rel="stylesheet" href="/assets/css/app.css">
    <?php echo Markly::renderHeadAssets(['css_href' => '/public/md-editor.css', 'js_src' => '/public/md-editor.js']); ?>
</head>
<body class="public-body">
    <div class="public-shell">
        <header class="public-header" role="banner">
            <div class="public-brand">
                <span class="public-logo" aria-hidden="true">✶</span>
                <h1 class="public-title"><?php echo htmlspecialchars(Constants::APP_NAME, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h1>
            </div>
            <nav class="public-nav" aria-label="Primary">
                <a href="/login.php" class="btn-link">Sign in</a>
            </nav>
        </header>
        <main class="public-note" role="main">
            <?php if ($note): ?>
                <article class="note-view" aria-label="Shared note">
                    <header class="note-view__header">
                        <h2><?php echo htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
                        <?php if ($note['tags'] !== ''): ?>
                            <ul class="tag-list" aria-label="Tags">
                                <?php foreach (explode(',', (string)$note['tags']) as $tag): ?>
                                    <li>#<?php echo htmlspecialchars($tag, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </header>
                    <article id="publicContent" data-raw="<?php echo htmlspecialchars($note['content'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" class="prose"></article>
                </article>
            <?php else: ?>
                <div class="empty-state" role="status">
                    <h2>Note not found</h2>
                    <p>This public note is no longer available.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <?php echo Markly::renderFootAssets(['css_href' => '/public/md-editor.css', 'js_src' => '/public/md-editor.js']); ?>
    <script>
    if (typeof marked !== 'undefined' && typeof DOMPurify !== 'undefined') {
        var el = document.getElementById('publicContent');
        if (el && el.dataset.raw) {
            marked.setOptions({gfm: true, breaks: false, mangle: false, headerIds: true});
            el.innerHTML = DOMPurify.sanitize(marked.parse(el.dataset.raw));
        }
    }
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(function () {
            var notice = document.createElement('div');
            notice.className = 'sw-fallback';
            notice.textContent = 'Offline mode is unavailable in this browser session.';
            document.body.appendChild(notice);
        });
    }
    </script>
</body>
</html>
<?php
    exit;
}

if (!$auth->check()) {
    header('Location: /login.php');
    exit;
}

$user = $auth->user();
$csrfToken = Csrf::issue();
$themeCookie = $_COOKIE[Constants::THEME_STORAGE_KEY] ?? '';
$initialTheme = $themeCookie === 'dark' ? 'dark' : 'light';
$boot = [
    'csrf' => $csrfToken,
    'user' => ['email' => $user['email']],
    'routes' => [
        'notes'   => '/api/notes.php',
        'auth'    => '/api/auth.php',
        'publish' => '/api/publish.php',
    ],
    'config' => [
        'base_url' => (string)($config['base_url'] ?? ''),
        'cache_version' => Constants::CACHE_VERSION,
    ],
    'app' => array_merge($config['app'], [
        'storage_prefix' => Constants::STORAGE_PREFIX,
        'theme_key' => Constants::THEME_STORAGE_KEY,
    ]),
];
?><!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($initialTheme, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars(Constants::APP_NAME . ' · Markdown notes', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></title>
    <meta name="theme-color" content="#f9fafb">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="stylesheet" href="/assets/css/app.css">
    <?php echo Markly::renderHeadAssets(['css_href' => '/public/md-editor.css', 'js_src' => '/public/md-editor.js']); ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
</head>
<body class="app-body">
    <svg xmlns="http://www.w3.org/2000/svg" style="display:none">
        <symbol id="icon-menu" viewBox="0 0 24 24" fill="none">
            <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </symbol>
        <symbol id="icon-x" viewBox="0 0 24 24" fill="none">
            <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </symbol>
        <symbol id="icon-search" viewBox="0 0 24 24" fill="none">
            <circle cx="11" cy="11" r="6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
            <path d="m20 20-3.5-3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </symbol>
        <symbol id="icon-plus" viewBox="0 0 24 24" fill="none">
            <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </symbol>
        <symbol id="icon-save" viewBox="0 0 24 24" fill="none">
            <path d="M5 4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8l-6-4H5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M9 4v6h6V4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            <circle cx="12" cy="16" r="2.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
        </symbol>
        <symbol id="icon-share" viewBox="0 0 24 24" fill="none">
            <path d="M7 12a5 5 0 0 1 5-5h4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M15.5 5 19 8.5 15.5 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M17 12a5 5 0 0 1-5 5H7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M8.5 17 5 13.5 8.5 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </symbol>
        <symbol id="icon-link" viewBox="0 0 24 24" fill="none">
            <path d="M10.5 13.5 9 15a4 4 0 1 1-5.657-5.657l2.122-2.121A4 4 0 0 1 12 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M13.5 10.5 15 9a4 4 0 0 1 5.657 5.657l-2.122 2.121A4 4 0 0 1 12 16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </symbol>
        <symbol id="icon-trash" viewBox="0 0 24 24" fill="none">
            <path d="M5 7h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M16 7v11a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2V7m3-3h2a2 2 0 0 1 2 2v1H9V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </symbol>
        <symbol id="icon-sun" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
            <path d="M12 2v2M12 20v2M4 12H2m20 0h-2M5 5l-1.5-1.5M20.5 20.5 19 19M5 19l-1.5 1.5M20.5 3.5 19 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </symbol>
        <symbol id="icon-moon" viewBox="0 0 24 24" fill="none">
            <path d="M21 12.8A9 9 0 1 1 11.2 3a6.5 6.5 0 0 0 9.8 9.8Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </symbol>
        <symbol id="icon-logout" viewBox="0 0 24 24" fill="none">
            <path d="M15 16.5 19.5 12 15 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M19.5 12H9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M12 19a7 7 0 1 1 0-14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </symbol>
        <symbol id="icon-arrow-right" viewBox="0 0 24 24" fill="none">
            <path d="M8 4.5 15.5 12 8 19.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </symbol>
        <symbol id="icon-eye" viewBox="0 0 24 24" fill="none">
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
        </symbol>
        <symbol id="icon-edit" viewBox="0 0 24 24" fill="none">
            <path d="M4 21h4l11-11a2.121 2.121 0 0 0-3-3L5 18v3Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M14 7l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </symbol>
        <symbol id="icon-layout" viewBox="0 0 24 24" fill="none">
            <rect x="4" y="5" width="16" height="14" rx="2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
            <path d="M12 5v14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </symbol>
        <symbol id="icon-bold" viewBox="0 0 24 24" fill="none">
            <path d="M7 5h6a3 3 0 0 1 0 6H7zM7 11h7a3 3 0 0 1 0 6H7z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" fill="none" />
        </symbol>
        <symbol id="icon-italic" viewBox="0 0 24 24" fill="none">
            <path d="M10 5h8M6 19h8M14 5l-4 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </symbol>
        <symbol id="icon-heading" viewBox="0 0 24 24" fill="none">
            <path d="M6 5v14M18 5v14M6 12h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </symbol>
        <symbol id="icon-list" viewBox="0 0 24 24" fill="none">
            <path d="M10 6h10M10 12h10M10 18h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            <circle cx="5" cy="6" r="1" fill="currentColor" />
            <circle cx="5" cy="12" r="1" fill="currentColor" />
            <circle cx="5" cy="18" r="1" fill="currentColor" />
        </symbol>
        <symbol id="icon-code" viewBox="0 0 24 24" fill="none">
            <path d="M8 18 3 12l5-6M16 6l5 6-5 6M14 4l-4 16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </symbol>
        <symbol id="icon-table" viewBox="0 0 24 24" fill="none">
            <rect x="4" y="5" width="16" height="14" rx="1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
            <path d="M4 11h16M10 5v14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </symbol>
        <symbol id="icon-focus" viewBox="0 0 24 24" fill="none">
            <path d="M12 5V3M12 21v-2M5 12H3M21 12h-2M7.5 7.5 5.9 5.9M18.1 18.1 16.5 16.5M7.5 16.5 5.9 18.1M18.1 5.9 16.5 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
        </symbol>
    </svg>
    <div class="app-shell" id="app" data-theme="<?php echo htmlspecialchars($initialTheme, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        <aside class="sidebar" id="sidebar" aria-label="Notes list">
            <div class="sidebar__inner">
                <div class="sidebar__header">
                    <div class="sidebar__brand">
                        <span class="sidebar__logo" aria-hidden="true">✶</span>
                        <div class="sidebar__brand-text">
                            <span class="sidebar__brand-name"><?php echo htmlspecialchars(Constants::APP_NAME, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                            <span class="sidebar__brand-tagline"><?php echo htmlspecialchars(Constants::APP_TAGLINE, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                        </div>
                    </div>
                    <button id="sidebarClose" class="icon-btn icon-btn--ghost sidebar__dismiss" aria-label="Close sidebar" title="Close sidebar">
                        <svg class="icon" aria-hidden="true"><use xlink:href="#icon-x"></use></svg>
                        <span class="sr-only">Close sidebar</span>
                    </button>
                </div>
                <button id="newNoteBtn" class="sidebar__new" aria-label="New note" title="Create a new note">
                    <svg class="icon" aria-hidden="true"><use xlink:href="#icon-plus"></use></svg>
                    <span>New note</span>
                </button>
                <div class="sidebar__search" role="search">
                    <label class="sr-only" for="searchInput">Search notes</label>
                    <svg class="icon sidebar__search-icon" aria-hidden="true"><use xlink:href="#icon-search"></use></svg>
                    <input type="search" id="searchInput" placeholder="Search notes" autocomplete="off">
                    <button id="searchClear" class="icon-btn icon-btn--ghost" aria-label="Clear search" title="Clear search">
                        <svg class="icon" aria-hidden="true"><use xlink:href="#icon-x"></use></svg>
                        <span class="sr-only">Clear search</span>
                    </button>
                </div>
                <div class="sidebar__filters" role="radiogroup" aria-label="Filter notes">
                    <button type="button" class="sidebar__filter is-active" data-filter="all" aria-pressed="true">All</button>
                    <button type="button" class="sidebar__filter" data-filter="favorites" aria-pressed="false">Favorites</button>
                    <button type="button" class="sidebar__filter" data-filter="public" aria-pressed="false">Public</button>
                    <button type="button" class="sidebar__filter" data-filter="drafts" aria-pressed="false">Drafts</button>
                </div>
                <div class="sidebar__section sidebar__section--notes">
                    <div class="sidebar__section-label">Notes</div>
                    <div class="sidebar__list" id="noteList" role="list"></div>
                </div>
                <div class="sidebar__section sidebar__section--tags" aria-live="polite">
                    <div class="sidebar__section-label">Tags</div>
                    <div class="sidebar__tags" id="tagList" aria-label="Filter by tag"></div>
                </div>
            </div>
        </aside>
        <div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>
        <main class="workspace" id="workspace">
            <header class="topbar" role="banner">
                <div class="topbar__primary">
                    <button id="sidebarToggle" class="icon-btn icon-btn--ghost topbar__toggle" aria-label="Toggle sidebar" aria-controls="sidebar" aria-expanded="false">
                        <svg class="icon" aria-hidden="true"><use xlink:href="#icon-menu"></use></svg>
                        <span class="sr-only">Toggle sidebar</span>
                    </button>
                    <div class="topbar__brand" data-tagline="<?php echo htmlspecialchars(Constants::APP_TAGLINE, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                        <span class="topbar__logo" aria-hidden="true">✶</span>
                        <span class="topbar__brand-name"><?php echo htmlspecialchars(Constants::APP_NAME, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                    </div>
                </div>
                <div class="topbar__actions">
                    <div class="topbar__visibility" role="group" aria-label="Visibility">
                        <span class="topbar__visibility-icon" aria-hidden="true">👁</span>
                        <label class="note-visibility" title="Toggle visibility">
                            <span class="sr-only">Toggle public visibility</span>
                            <input type="checkbox" id="notePublic">
                            <span class="note-visibility__pill" aria-hidden="true">
                                <span class="note-visibility__option note-visibility__option--private">Private</span>
                                <span class="note-visibility__option note-visibility__option--public">Public</span>
                                <span class="note-visibility__thumb"></span>
                            </span>
                        </label>
                    </div>
                    <div class="topbar__group topbar__group--context" role="group" aria-label="Note actions">
                        <button id="saveBtn" class="btn btn--primary" type="button" title="Save changes">
                            <svg class="icon" aria-hidden="true"><use xlink:href="#icon-save"></use></svg>
                            <span class="btn__label" data-default="Save">Save</span>
                        </button>
                        <button id="deleteBtn" class="btn btn--outline btn--danger" type="button" title="Delete note">
                            <svg class="icon" aria-hidden="true"><use xlink:href="#icon-trash"></use></svg>
                            <span class="sr-only">Delete note</span>
                        </button>
                        <button id="shareBtn" class="btn btn--ghost" type="button" aria-pressed="false" title="Share publicly">
                            <svg class="icon" aria-hidden="true"><use xlink:href="#icon-share"></use></svg>
                            <span class="sr-only">Toggle public link</span>
                        </button>
                    </div>
                    <div class="topbar__group topbar__group--utility" role="group" aria-label="Workspace actions">
                        <button id="focusToggle" class="control-btn control-btn--ghost" type="button" aria-pressed="false" title="Toggle focus mode (Ctrl+Shift+F)">
                            <svg class="icon" aria-hidden="true"><use xlink:href="#icon-focus"></use></svg>
                            <span class="sr-only">Toggle focus mode</span>
                        </button>
                        <button id="themeToggle" class="control-btn control-btn--ghost" aria-label="Toggle theme" title="Toggle theme">
                            <svg class="icon" aria-hidden="true"><use xlink:href="#icon-sun"></use></svg>
                            <span class="sr-only">Toggle theme</span>
                        </button>
                        <button id="logoutBtn" class="control-btn control-btn--ghost" aria-label="Sign out" title="Sign out">
                            <svg class="icon" aria-hidden="true"><use xlink:href="#icon-logout"></use></svg>
                            <span class="sr-only">Sign out</span>
                        </button>
                    </div>
                </div>
            </header>
            
            <section class="note-meta" aria-label="Note metadata">
                <label class="note-meta__title">
                    <span class="sr-only">Title</span>
                    <input id="noteTitle" type="text" placeholder="Untitled note" autocomplete="off">
                </label>
                <details class="note-meta__details" id="metaDetails">
                    <summary class="note-meta__summary">
                        <span class="note-meta__summary-icon" aria-hidden="true">ℹ</span>
                        <span>Metadata</span>
                    </summary>
                    <div class="note-meta__fields">
                        <label class="note-meta__field">
                            <span class="note-meta__icon" aria-hidden="true">🔗</span>
                            <span class="note-meta__label">Slug</span>
                            <input id="noteSlug" type="text" placeholder="auto-generated" autocomplete="off">
                        </label>
                        <label class="note-meta__field">
                            <span class="note-meta__icon" aria-hidden="true">#</span>
                            <span class="note-meta__label">Tags</span>
                            <input id="noteTags" type="text" placeholder="comma,separated" autocomplete="off">
                        </label>
                    </div>
                </details>
            </section>


            <section class="editor-shell" aria-label="Editor">
                <div class="editor-surface">
                    <?php echo Markly::render(); ?>
                </div>
            </section>
            <section class="backlinks" id="backlinks" aria-label="Backlinks"></section>
            <footer class="workspace-footer" id="workspaceFooter" aria-live="polite">
                <div class="workspace-footer__status">
                    <span class="workspace-footer__pulse" aria-hidden="true"></span>
                    <span id="noteStatus">Draft</span>
                </div>
                <div class="workspace-footer__stats" id="stats">0 words · 0 characters · 0 lines</div>
            </footer>
            <nav class="mobile-actions" aria-label="Quick actions">
                <button type="button" class="mobile-actions__btn mobile-actions__btn--primary" data-quick-action="save">
                    <svg class="icon" aria-hidden="true"><use xlink:href="#icon-save"></use></svg>
                    <span>Save</span>
                </button>
                <button type="button" class="mobile-actions__btn" data-quick-action="new">
                    <svg class="icon" aria-hidden="true"><use xlink:href="#icon-plus"></use></svg>
                    <span>New</span>
                </button>
                <button type="button" class="mobile-actions__btn" data-quick-action="theme">
                    <svg class="icon" aria-hidden="true"><use xlink:href="#icon-sun"></use></svg>
                    <span>Theme</span>
                </button>
            </nav>
        </main>
    </div>
    <div id="toastContainer" class="toast-container" aria-live="polite" aria-atomic="true"></div>
    <footer class="app-footer">Markly by Dominic Minischetti · Built without frameworks.</footer>
    <template id="noteListItem">
        <button class="note-item" data-slug="">
            <span class="note-item__title"></span>
            <span class="note-item__meta"></span>
            <span class="note-item__chevron" aria-hidden="true">
                <svg class="icon" aria-hidden="true"><use xlink:href="#icon-arrow-right"></use></svg>
            </span>
        </button>
    </template>
    <?php echo Markly::renderFootAssets(['css_href' => '/public/md-editor.css', 'js_src' => '/public/md-editor.js']); ?>
    <script>window.MARKLY_BOOT = <?php echo json_encode($boot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;</script>
    <script type="module" src="/assets/js/app.js"></script>
</body>
</html>
