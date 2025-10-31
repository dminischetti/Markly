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
    <header class="public-header">
        <h1><?php echo htmlspecialchars(Constants::APP_NAME, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h1>
        <nav><a href="/login.php" class="btn-link">Sign in</a></nav>
    </header>
    <main class="public-note" role="main">
        <?php if ($note): ?>
            <article class="note-view">
                <header>
                    <h2><?php echo htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
                    <?php if ($note['tags'] !== ''): ?>
                        <ul class="tag-list">
                            <?php foreach (explode(',', (string)$note['tags']) as $tag): ?>
                                <li>#<?php echo htmlspecialchars($tag, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </header>
                <article id="publicContent" data-raw="<?php echo htmlspecialchars($note['content'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" class="prose"></article>
            </article>
        <?php else: ?>
            <div class="empty-state">
                <h2>Note not found</h2>
                <p>This public note is no longer available.</p>
            </div>
        <?php endif; ?>
    </main>
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
    <div class="app-shell" id="app" data-theme="<?php echo htmlspecialchars($initialTheme, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        <aside class="sidebar" id="sidebar" aria-label="Notes list">
            <div class="sidebar__header">
                <h1><?php echo htmlspecialchars(Constants::APP_NAME, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h1>
                <button id="sidebarClose" class="icon-btn" aria-label="Close sidebar">✕</button>
            </div>
            <p class="sidebar__tagline"><?php echo htmlspecialchars(Constants::APP_TAGLINE, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
            <div class="sidebar__search">
                <input type="search" id="searchInput" placeholder="Search notes" autocomplete="off">
                <button id="searchClear" class="icon-btn" aria-label="Clear search">⌫</button>
            </div>
            <div class="sidebar__tags" id="tagList" aria-label="Filter by tag"></div>
            <div class="sidebar__list" id="noteList" role="list"></div>
            <button id="newNoteBtn" class="fab" aria-label="New note">+</button>
        </aside>
        <div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>
        <main class="workspace" id="workspace">
            <header class="topbar">
                <button id="sidebarToggle" class="icon-btn" aria-label="Toggle sidebar" aria-controls="sidebar" aria-expanded="false">☰</button>
                <div class="crumb">
                    <span id="noteStatus">Draft</span>
                </div>
                <div class="topbar__actions">
                    <button id="themeToggle" class="icon-btn" aria-label="Toggle theme">🌓</button>
                    <button id="deleteBtn" class="icon-btn" aria-label="Delete note">🗑</button>
                    <button id="shareBtn" class="icon-btn" aria-label="Toggle public link">🔗</button>
                    <button id="logoutBtn" class="icon-btn" aria-label="Sign out">⎋</button>
                </div>
            </header>
            <section class="note-meta" aria-label="Note metadata">
                <label>
                    <span class="label">Title</span>
                    <input id="noteTitle" type="text" placeholder="Untitled note" autocomplete="off">
                </label>
                <label>
                    <span class="label">Slug</span>
                    <input id="noteSlug" type="text" placeholder="auto-generated" autocomplete="off">
                </label>
                <label>
                    <span class="label">Tags</span>
                    <input id="noteTags" type="text" placeholder="comma,separated" autocomplete="off">
                </label>
                <label class="toggle">
                    <input type="checkbox" id="notePublic">
                    <span>Public</span>
                </label>
            </section>
            <section class="editor-shell" aria-label="Editor">
                <?php echo Markly::render(); ?>
            </section>
            <section class="backlinks" id="backlinks" aria-label="Backlinks"></section>
        </main>
    </div>
    <div id="toastContainer" class="toast-container" aria-live="polite" aria-atomic="true"></div>
    <footer class="app-footer">Markly by Dominic Minischetti · Built without frameworks.</footer>
    <template id="noteListItem">
        <button class="note-item" data-slug="">
            <span class="note-item__title"></span>
            <span class="note-item__meta"></span>
        </button>
    </template>
    <?php echo Markly::renderFootAssets(['css_href' => '/public/md-editor.css', 'js_src' => '/public/md-editor.js']); ?>
    <script>window.MARKLY_BOOT = <?php echo json_encode($boot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;</script>
    <script type="module" src="/assets/js/app.js"></script>
</body>
</html>
