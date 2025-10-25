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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.4/dist/tailwind.min.css">
    <script src="https://unpkg.com/@phosphor-icons/web@2.1.1"></script>
    <script defer src="https://unpkg.com/alpinejs@3.13.5/dist/cdn.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" integrity="sha512-+z3O8DqRL3OjaxAg/P6nxsVXni4eWh05rq6ArlTc95xJ3Adxpv8uKXuX4nHCqB6f+GO6zkRgZNpmjDoE7YOhkA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link rel="stylesheet" href="/assets/css/app.css">
    <?php echo Markly::renderHeadAssets(['css_href' => '/public/md-editor.css', 'js_src' => '/public/md-editor.js']); ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
</head>
<body class="app-body" x-data="marklyLayout()" x-init="init()" x-bind:class="{'is-compact': compact}">
    <div class="app-shell" id="app" data-theme="<?php echo htmlspecialchars($initialTheme, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        <aside class="sidebar" id="sidebar" aria-label="Notes list">
            <div class="sidebar__inner">
                <div class="sidebar__header">
                    <div class="sidebar__brand" x-data="collapsible(true)">
                        <button type="button" class="sidebar__brand-button" x-on:click="toggle()" aria-expanded="true" :aria-expanded="open ? 'true' : 'false'">
                            <span class="sidebar__logo" aria-hidden="true">●</span>
                            <div class="sidebar__brand-text">
                                <span class="sidebar__brand-name"><?php echo htmlspecialchars(Constants::APP_NAME, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                                <span class="sidebar__brand-tagline"><?php echo htmlspecialchars(Constants::APP_TAGLINE, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                            </div>
                            <i class="ph ph-caret-down sidebar__brand-caret" aria-hidden="true"></i>
                        </button>
                        <div x-ref="section" class="sidebar__brand-meta">
                            <p>Keep every note ready offline.</p>
                        </div>
                    </div>
                    <button id="sidebarClose" class="icon-btn icon-btn--ghost sidebar__dismiss" aria-label="Close sidebar" title="Close sidebar">
                        <i class="ph ph-x"></i>
                        <span class="sr-only">Close sidebar</span>
                    </button>
                </div>
                <button id="newNoteBtn" class="sidebar__new" aria-label="New note" title="Create a new note">
                    <i class="ph ph-plus"></i>
                    <span>New note</span>
                </button>
                <div class="sidebar__search" role="search">
                    <label class="sr-only" for="searchInput">Search notes</label>
                    <i class="ph ph-magnifying-glass sidebar__search-icon" aria-hidden="true"></i>
                    <input type="search" id="searchInput" placeholder="Search notes" autocomplete="off">
                    <button id="searchClear" class="icon-btn icon-btn--ghost" aria-label="Clear search" title="Clear search">
                        <i class="ph ph-x"></i>
                        <span class="sr-only">Clear search</span>
                    </button>
                </div>
                <div class="sidebar__filters" role="radiogroup" aria-label="Filter notes">
                    <button type="button" class="sidebar__filter is-active" data-filter="all" aria-pressed="true">
                        <i class="ph ph-squares-four" aria-hidden="true"></i>
                        <span>All</span>
                    </button>
                    <button type="button" class="sidebar__filter" data-filter="favorites" aria-pressed="false">
                        <i class="ph ph-star" aria-hidden="true"></i>
                        <span>Favorites</span>
                    </button>
                    <button type="button" class="sidebar__filter" data-filter="public" aria-pressed="false">
                        <i class="ph ph-globe" aria-hidden="true"></i>
                        <span>Public</span>
                    </button>
                    <button type="button" class="sidebar__filter" data-filter="drafts" aria-pressed="false">
                        <i class="ph ph-note" aria-hidden="true"></i>
                        <span>Drafts</span>
                    </button>
                </div>
                <div class="sidebar__section sidebar__section--notes" x-data="collapsible(true)">
                    <button type="button" class="sidebar__section-label" x-on:click="toggle()" :aria-expanded="open ? 'true' : 'false'">
                        <span class="sidebar__section-icon" aria-hidden="true"><i class="ph ph-notepad"></i></span>
                        <span>Notes</span>
                        <i class="ph ph-caret-down"></i>
                    </button>
                    <div class="sidebar__list" id="noteList" role="list" x-ref="section"></div>
                </div>
                <div class="sidebar__section sidebar__section--tags" aria-live="polite" x-data="collapsible(true)">
                    <button type="button" class="sidebar__section-label" x-on:click="toggle()" :aria-expanded="open ? 'true' : 'false'">
                        <span class="sidebar__section-icon" aria-hidden="true"><i class="ph ph-hash"></i></span>
                        <span>Tags</span>
                        <i class="ph ph-caret-down"></i>
                    </button>
                    <div class="sidebar__tags" id="tagList" aria-label="Filter by tag" x-ref="section"></div>
                </div>
            </div>
        </aside>
        <div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>
        <main class="workspace" id="workspace">
            <header class="topbar" role="banner">
                <div class="topbar__primary">
                    <button id="sidebarToggle" class="icon-btn icon-btn--ghost topbar__toggle" aria-label="Toggle sidebar" aria-controls="sidebar" aria-expanded="false">
                        <i class="ph ph-list"></i>
                        <span class="sr-only">Toggle sidebar</span>
                    </button>
                    <div class="topbar__brand" data-tagline="<?php echo htmlspecialchars(Constants::APP_TAGLINE, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                        <span class="topbar__logo" aria-hidden="true">●</span>
                        <div class="topbar__brand-copy">
                            <span class="topbar__brand-name"><?php echo htmlspecialchars(Constants::APP_NAME, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                            <span class="topbar__brand-tagline"><?php echo htmlspecialchars(Constants::APP_TAGLINE, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                        </div>
                        <span class="topbar__brand-pulse" id="brandPulse" aria-hidden="true"></span>
                    </div>
                </div>
                <div class="topbar__actions">
                    <div class="topbar__cluster topbar__cluster--primary" role="group" aria-label="Primary note actions">
                        <input type="checkbox" id="notePublic" class="note-visibility__input">
                        <button id="shareBtn" class="note-visibility" type="button" aria-pressed="false" title="Toggle visibility">
                            <span class="note-visibility__label note-visibility__label--private">Private</span>
                            <span class="note-visibility__thumb" aria-hidden="true"></span>
                            <span class="note-visibility__label note-visibility__label--public">Public</span>
                        </button>
                        <button id="saveBtn" class="btn btn--primary" type="button" title="Save changes">
                            <i class="ph ph-floppy-disk"></i>
                            <span class="btn__label" data-default="Save">Save</span>
                        </button>
                        <button id="deleteBtn" class="btn btn--outline btn--danger" type="button" title="Delete note">
                            <i class="ph ph-trash"></i>
                            <span class="sr-only">Delete note</span>
                        </button>
                    </div>
                    <div class="topbar__cluster topbar__cluster--secondary" role="group" aria-label="Workspace utilities">
                        <button id="undoBtn" class="control-btn" type="button" title="Undo (Ctrl/Cmd+Z)">
                            <i class="ph ph-arrow-counter-clockwise"></i>
                            <span class="sr-only">Undo</span>
                        </button>
                        <button id="redoBtn" class="control-btn" type="button" title="Redo (Ctrl/Cmd+Shift+Z)">
                            <i class="ph ph-arrow-clockwise"></i>
                            <span class="sr-only">Redo</span>
                        </button>
                        <button id="focusToggle" class="control-btn" type="button" aria-pressed="false" title="Toggle focus mode (Ctrl+Shift+F)">
                            <i class="ph ph-corners-out"></i>
                            <span class="sr-only">Toggle focus mode</span>
                        </button>
                        <button id="themeToggle" class="control-btn" aria-label="Toggle theme" title="Toggle theme">
                            <i id="themeToggleIcon" class="ph ph-sun-dim"></i>
                            <span class="sr-only">Toggle theme</span>
                        </button>
                        <button id="settingsBtn" class="control-btn" type="button" title="Toggle metadata">
                            <i class="ph ph-sliders"></i>
                            <span class="sr-only">Toggle metadata panel</span>
                        </button>
                        <button id="logoutBtn" class="control-btn" aria-label="Sign out" title="Sign out">
                            <i class="ph ph-sign-out"></i>
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
                        <span class="note-meta__summary-icon" aria-hidden="true"><i class="ph ph-info"></i></span>
                        <span>Metadata</span>
                    </summary>
                    <div class="note-meta__fields">
                        <label class="note-meta__field">
                            <span class="note-meta__icon" aria-hidden="true"><i class="ph ph-link-simple"></i></span>
                            <span class="note-meta__label">Slug</span>
                            <input id="noteSlug" type="text" placeholder="auto-generated" autocomplete="off">
                        </label>
                        <label class="note-meta__field">
                            <span class="note-meta__icon" aria-hidden="true"><i class="ph ph-hash"></i></span>
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
                <button type="button" class="mobile-actions__btn mobile-actions__btn--primary" data-quick-action="save" title="Save note">
                    <i class="ph ph-floppy-disk"></i>
                    <span>Save</span>
                </button>
                <button type="button" class="mobile-actions__btn" data-quick-action="visibility" title="Toggle visibility">
                    <i class="ph ph-eye"></i>
                    <span>Share</span>
                </button>
                <button type="button" class="mobile-actions__btn" data-quick-action="delete" title="Delete note">
                    <i class="ph ph-trash"></i>
                    <span>Delete</span>
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
                <i class="ph ph-caret-right"></i>
            </span>
        </button>
    </template>
    <?php echo Markly::renderFootAssets(['css_href' => '/public/md-editor.css', 'js_src' => '/public/md-editor.js']); ?>
    <script>window.MARKLY_BOOT = <?php echo json_encode($boot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;</script>
    <script type="module" src="/assets/js/app.js"></script>
</body>
</html>
