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
<html lang="en" data-theme="<?php echo htmlspecialchars($initialTheme, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" x-data="marklyShell()" x-bind:data-theme="theme" x-bind:class="{'dark': theme === 'dark'}" x-init="init()">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars(Constants::APP_NAME . ' · Markdown notes', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></title>
    <meta name="theme-color" content="#f9fafb">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
    <script>
        (function () {
            var cookieTheme = <?php echo json_encode($initialTheme, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
            var storedTheme = null;
            try {
                storedTheme = window.localStorage ? localStorage.getItem('<?php echo addslashes(Constants::THEME_STORAGE_KEY); ?>') : null;
            } catch (err) {
                storedTheme = null;
            }
            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            var theme = storedTheme || cookieTheme || (prefersDark ? 'dark' : 'light');
            document.documentElement.dataset.theme = theme;
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
    <script>
        window.tailwind = window.tailwind || {};
        window.tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        surface: {
                            DEFAULT: 'var(--color-surface)',
                            muted: 'var(--color-surface-muted)',
                            contrast: 'var(--color-surface-contrast)',
                        },
                        slate: {
                            925: 'var(--color-slate-925)'
                        },
                        border: {
                            DEFAULT: 'var(--color-border)',
                            strong: 'var(--color-border-strong)'
                        },
                        accent: {
                            DEFAULT: 'var(--color-accent)',
                            foreground: 'var(--color-accent-foreground)'
                        },
                        elevated: {
                            light: 'var(--color-elevated-light)',
                            dark: 'var(--color-elevated-dark)'
                        }
                    },
                    boxShadow: {
                        soft: '0 12px 45px -20px rgba(15, 23, 42, 0.35)',
                        ring: '0 0 0 1px rgba(99, 102, 241, 0.2), 0 18px 40px -28px rgba(99, 102, 241, 0.65)'
                    },
                    borderRadius: {
                        xl: '1rem'
                    }
                }
            }
        };
    </script>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <style type="text/tailwindcss">
        @layer base {
            :root {
                color-scheme: light;
                --color-surface: #f8fafc;
                --color-surface-muted: #f1f5f9;
                --color-surface-contrast: #0f172a;
                --color-slate-925: #0b1120;
                --color-border: rgba(15, 23, 42, 0.08);
                --color-border-strong: rgba(15, 23, 42, 0.14);
                --color-accent: #6366f1;
                --color-accent-foreground: #f8fafc;
                --color-elevated-light: #ffffff;
                --color-elevated-dark: #16213a;
                --color-toast-bg: rgba(255, 255, 255, 0.95);
                --color-toast-text: #1f2937;
                --shadow-accent-strong: rgba(99, 102, 241, 0.45);
                --shadow-accent-soft: rgba(99, 102, 241, 0);
            }
            .dark {
                color-scheme: dark;
                --color-surface: #0b1120;
                --color-surface-muted: #16213a;
                --color-surface-contrast: #f8fafc;
                --color-border: rgba(255, 255, 255, 0.12);
                --color-border-strong: rgba(255, 255, 255, 0.24);
                --color-toast-bg: rgba(22, 33, 58, 0.9);
                --color-toast-text: #e2e8f0;
                --shadow-accent-strong: rgba(99, 102, 241, 0.55);
                --shadow-accent-soft: rgba(99, 102, 241, 0);
            }
            body {
                @apply bg-surface text-slate-900 antialiased selection:bg-accent/20 selection:text-accent-foreground;
            }
            .dark body {
                @apply bg-slate-925 text-slate-100 selection:bg-accent/30 selection:text-accent-foreground;
            }
            h1, h2, h3, h4, h5, h6 {
                @apply font-semibold text-slate-900 dark:text-white;
            }
        }

        @layer components {
            .btn-primary {
                @apply inline-flex items-center gap-2 rounded-full border border-transparent bg-accent px-4 py-2 text-sm font-medium text-accent-foreground shadow-soft transition-all duration-200 ease-out hover:-translate-y-0.5 hover:shadow-ring focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface;
            }
            .btn-outline {
                @apply inline-flex items-center gap-2 rounded-full border border-border bg-white/80 px-4 py-2 text-sm font-medium text-slate-900 transition-all duration-200 hover:border-border-strong hover:bg-slate-900/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface dark:border-white/10 dark:bg-white/10 dark:text-slate-100;
            }
            .icon-btn {
                @apply inline-flex h-10 w-10 items-center justify-center rounded-full border border-transparent text-lg text-slate-500 transition-colors duration-200 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface dark:text-slate-400 dark:hover:text-white;
            }
            .pill-muted {
                @apply inline-flex items-center gap-2 rounded-full bg-surface-muted px-4 py-2 text-sm font-medium text-slate-600 transition-all duration-200 hover:bg-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface dark:bg-white/10 dark:text-slate-200 dark:hover:bg-white/20;
            }
            .pill-active {
                @apply inline-flex items-center gap-2 rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-soft focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface dark:bg-white dark:text-slate-900;
            }
            .badge {
                @apply inline-flex items-center gap-1 rounded-full bg-surface-muted px-3 py-1 text-xs font-medium text-slate-500 dark:bg-white/10 dark:text-slate-200;
            }
            .toast-card {
                @apply pointer-events-auto rounded-2xl border border-border bg-white/95 px-4 py-3 text-sm font-medium text-slate-700 shadow-soft dark:border-white/10 dark:bg-white/10 dark:text-slate-200;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simplemde@1.11.2/dist/simplemde.min.css">
    <script src="https://unpkg.com/@phosphor-icons/web@2.1.1"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" integrity="sha512-+z3O8DqRL3OjaxAg/P6nxsVXni4eWh05rq6ArlTc95xJ3Adxpv8uKXuX4nHCqB6f+GO6zkRgZNpmjDoE7YOhkA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdn.jsdelivr.net/npm/simplemde@1.11.2/dist/simplemde.min.js"></script>
    <script defer src="https://unpkg.com/alpinejs@3.13.5/dist/cdn.min.js"></script>
    <?php echo Markly::renderHeadAssets(['css_href' => '/public/md-editor.css', 'js_src' => '/public/md-editor.js']); ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
</head>
<body class="min-h-screen bg-surface text-slate-900 transition-colors duration-300 dark:bg-slate-925 dark:text-slate-100">
    <div class="relative flex min-h-screen overflow-hidden bg-surface transition-colors duration-300 dark:bg-slate-925">
        <div id="sidebarOverlay" class="fixed inset-0 z-30 hidden bg-slate-900/40 backdrop-blur-sm transition-opacity lg:hidden" aria-hidden="true"></div>
        <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-[min(360px,92vw)] -translate-x-full flex-col overflow-y-auto border-r border-border bg-white/80 px-6 pb-8 pt-6 shadow-soft backdrop-blur transition-transform duration-300 ease-out dark:border-white/10 dark:bg-white/5 lg:static lg:translate-x-0" aria-label="Notes list">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-900 text-lg text-white shadow-soft dark:bg-white dark:text-slate-900" aria-hidden="true">●</span>
                    <div>
                        <h1 class="text-base font-semibold tracking-tight text-slate-900 dark:text-white"><?php echo htmlspecialchars(Constants::APP_NAME, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h1>
                        <p class="text-sm text-slate-500 dark:text-slate-300"><?php echo htmlspecialchars(Constants::APP_TAGLINE, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                    </div>
                </div>
                <button type="button" id="sidebarClose" class="icon-btn lg:hidden" aria-label="Close sidebar">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            <p class="mt-5 rounded-2xl border border-dashed border-border/70 bg-white/60 p-4 text-sm text-slate-600 shadow-inner dark:border-white/10 dark:bg-white/5 dark:text-slate-200">Keep every idea ready offline. Notes sync when you're connected.</p>
            <button id="newNoteBtn" class="btn-primary mt-6 w-full justify-center" type="button">
                <i class="ph ph-plus"></i>
                <span>New note</span>
            </button>
            <div class="mt-6 flex items-center gap-3 rounded-full border border-border bg-white/70 px-4 py-2.5 shadow-inner focus-within:border-accent focus-within:ring-2 focus-within:ring-accent/20 dark:border-white/10 dark:bg-white/10">
                <i class="ph ph-magnifying-glass text-slate-500 dark:text-slate-300" aria-hidden="true"></i>
                <label for="searchInput" class="sr-only">Search notes</label>
                <input id="searchInput" type="search" placeholder="Search notes" autocomplete="off" class="w-full border-none bg-transparent text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none dark:text-white dark:placeholder:text-slate-500">
                <button id="searchClear" class="icon-btn h-8 w-8 text-base hidden" type="button" aria-label="Clear search">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            <div class="mt-6 flex flex-wrap gap-2" role="radiogroup" aria-label="Filter notes">
                <button type="button" class="pill-active" data-filter="all" aria-pressed="true">
                    <i class="ph ph-squares-four"></i>
                    <span>All</span>
                </button>
                <button type="button" class="pill-muted" data-filter="favorites" aria-pressed="false">
                    <i class="ph ph-star"></i>
                    <span>Favorites</span>
                </button>
                <button type="button" class="pill-muted" data-filter="public" aria-pressed="false">
                    <i class="ph ph-globe"></i>
                    <span>Public</span>
                </button>
                <button type="button" class="pill-muted" data-filter="drafts" aria-pressed="false">
                    <i class="ph ph-note"></i>
                    <span>Drafts</span>
                </button>
            </div>
            <div class="mt-8 space-y-8">
                <section class="space-y-4 rounded-2xl border border-border bg-white/70 p-5 shadow-soft backdrop-blur dark:border-white/10 dark:bg-white/5" aria-labelledby="notes-heading">
                    <header class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-900/5 text-lg text-slate-500 dark:bg-white/10 dark:text-slate-200"><i class="ph ph-notepad"></i></span>
                            <h2 id="notes-heading" class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Notes</h2>
                        </div>
                        <span id="notesCount" class="badge">0 items</span>
                    </header>
                    <div id="noteList" class="mt-4 space-y-2" role="list"></div>
                </section>
                <section class="space-y-4 rounded-2xl border border-border bg-white/70 p-5 shadow-soft backdrop-blur dark:border-white/10 dark:bg-white/5" aria-labelledby="tags-heading">
                    <header class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-900/5 text-lg text-slate-500 dark:bg-white/10 dark:text-slate-200"><i class="ph ph-hash"></i></span>
                            <h2 id="tags-heading" class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Tags</h2>
                        </div>
                        <span id="tagsCount" class="badge">0 tags</span>
                    </header>
                    <div id="tagList" class="mt-4 flex flex-wrap gap-2" aria-label="Filter by tag"></div>
                </section>
            </div>
        </aside>
        <div class="flex min-h-screen flex-1 flex-col bg-white/80 pl-0 transition-colors duration-300 dark:bg-white/5 lg:pl-0">
            <header class="sticky top-0 z-20 border-b border-border/70 bg-white/90 px-4 py-4 backdrop-blur transition-colors duration-300 dark:border-white/10 dark:bg-slate-925/80 sm:px-6 lg:px-10">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-1 items-center gap-3">
                        <button id="sidebarToggle" class="icon-btn -ml-1 lg:hidden" type="button" aria-label="Toggle sidebar">
                            <i class="ph ph-list"></i>
                        </button>
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-900 text-white shadow-soft dark:bg-white dark:text-slate-900" aria-hidden="true">●</span>
                            <div>
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Workspace</p>
                                <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Premium Markdown Notes</h1>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                        <input type="checkbox" id="notePublic" class="sr-only">
                        <div class="inline-flex items-center rounded-full border border-border bg-white/70 p-1 text-xs font-medium shadow-inner dark:border-white/10 dark:bg-white/10">
                            <button type="button" id="visibilityPrivate" class="pill-muted px-3 py-1" aria-pressed="true">Private</button>
                            <button type="button" id="visibilityPublic" class="pill-muted px-3 py-1" aria-pressed="false">Public</button>
                        </div>
                        <button id="shareBtn" class="btn-outline" type="button">
                            <i class="ph ph-share-network"></i>
                            Share
                        </button>
                        <button id="saveBtn" class="btn-primary" type="button">
                            <i class="ph ph-floppy-disk"></i>
                            <span class="btn__label" data-default="Save">Save</span>
                        </button>
                        <button id="deleteBtn" class="icon-btn border border-border/60 text-red-500 hover:bg-red-500/10" type="button" aria-label="Delete note">
                            <i class="ph ph-trash"></i>
                        </button>
                        <button id="themeToggle" class="icon-btn" type="button" aria-label="Toggle theme">
                            <i id="themeToggleIcon" class="ph ph-sun-dim"></i>
                        </button>
                        <button id="settingsBtn" class="icon-btn" type="button" aria-label="Toggle metadata">
                            <i class="ph ph-sliders"></i>
                        </button>
                        <button id="logoutBtn" class="icon-btn" type="button" aria-label="Sign out">
                            <i class="ph ph-sign-out"></i>
                        </button>
                    </div>
                </div>
            </header>
            <main id="workspace" class="flex flex-1 flex-col gap-8 px-4 pb-24 pt-6 transition-colors duration-300 dark:text-slate-100 sm:px-6 lg:px-10 lg:pb-12">
                <section class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex-1">
                        <label for="noteTitle" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Title</label>
                        <input id="noteTitle" type="text" placeholder="Untitled masterpiece" autocomplete="off" class="mt-2 w-full rounded-2xl border border-border bg-white/80 px-4 py-3 text-lg font-semibold text-slate-900 shadow-soft focus:border-accent focus:ring-4 focus:ring-accent/15 dark:border-white/10 dark:bg-white/10 dark:text-white">
                    </div>
                    <div class="flex items-center gap-3 text-sm text-slate-500 dark:text-slate-300">
                        <div class="flex items-center gap-2 rounded-full border border-border px-4 py-2 dark:border-white/10">
                            <i class="ph ph-clock"></i>
                            <span id="noteEdited">Just now</span>
                        </div>
                        <div class="flex items-center gap-2 rounded-full border border-border px-4 py-2 dark:border-white/10">
                            <i class="ph ph-chart-bar"></i>
                            <span id="noteMetrics">0 words</span>
                        </div>
                    </div>
                </section>
                <section class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                    <div class="markdown-editor-card overflow-hidden rounded-3xl border border-border bg-white/90 shadow-soft transition dark:border-white/10 dark:bg-white/10">
                        <?php echo Markly::render(); ?>
                    </div>
                    <aside class="flex flex-col gap-6">
                        <section id="metadataPanel" class="rounded-3xl border border-border bg-white/90 p-6 shadow-soft transition dark:border-white/10 dark:bg-white/10" data-open="true">
                            <button type="button" class="flex w-full items-center justify-between rounded-2xl text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface" aria-expanded="true">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Metadata</p>
                                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">Publish settings</h2>
                                </div>
                                <i class="ph ph-caret-down text-lg transition" id="metadataCaret"></i>
                            </button>
                            <div class="metadata-fields mt-5 space-y-4">
                                <label class="block text-sm font-medium text-slate-600 dark:text-slate-200">
                                    <span class="mb-2 flex items-center gap-2 text-xs uppercase tracking-wide text-slate-400"><i class="ph ph-link-simple"></i> Slug</span>
                                    <input id="noteSlug" type="text" placeholder="auto-generated" autocomplete="off" class="w-full rounded-2xl border border-border bg-white/80 px-4 py-2.5 text-sm text-slate-900 focus:border-accent focus:outline-none focus:ring-4 focus:ring-accent/10 dark:border-white/10 dark:bg-white/10 dark:text-white">
                                </label>
                                <label class="block text-sm font-medium text-slate-600 dark:text-slate-200">
                                    <span class="mb-2 flex items-center gap-2 text-xs uppercase tracking-wide text-slate-400"><i class="ph ph-hash"></i> Tags</span>
                                    <input id="noteTags" type="text" placeholder="research,writing" autocomplete="off" class="w-full rounded-2xl border border-border bg-white/80 px-4 py-2.5 text-sm text-slate-900 focus:border-accent focus:outline-none focus:ring-4 focus:ring-accent/10 dark:border-white/10 dark:bg-white/10 dark:text-white">
                                </label>
                                <div class="flex items-center justify-between rounded-2xl border border-border bg-white/60 px-4 py-3 text-xs uppercase tracking-wide text-slate-500 dark:border-white/10 dark:bg-white/5">
                                    <span>Shareable link</span>
                                    <button type="button" id="copyLinkBtn" class="pill-muted px-3 py-1 text-xs">
                                        <i class="ph ph-copy"></i>
                                        Copy
                                    </button>
                                </div>
                            </div>
                        </section>
                        <section class="rounded-3xl border border-border bg-gradient-to-br from-white/90 via-white/60 to-white/40 p-6 shadow-soft transition dark:border-white/10 dark:from-white/10 dark:via-white/5 dark:to-white/5">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Backlinks</p>
                            <div id="backlinks" class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300"></div>
                        </section>
                    </aside>
                </section>
            </main>
            <footer class="sticky bottom-0 z-10 border-t border-border/70 bg-white/90 px-4 py-4 backdrop-blur dark:border-white/10 dark:bg-slate-925/90 sm:px-6 lg:px-10">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-3 text-sm text-slate-500 dark:text-slate-300" id="statusCard">
                        <span id="statusPulse" class="inline-flex h-2.5 w-2.5 rounded-full bg-accent opacity-70"></span>
                        <span id="noteStatus">Draft</span>
                    </div>
                    <div id="stats" class="text-xs uppercase tracking-[0.2em] text-slate-400">0 words · 0 characters · 0 lines</div>
                </div>
            </footer>
            <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-border/70 bg-white/95 px-4 py-3 shadow-soft backdrop-blur dark:border-white/10 dark:bg-slate-925/95 lg:hidden" aria-label="Quick actions">
                <div class="grid grid-cols-3 gap-3 text-sm font-medium">
                    <button type="button" class="flex flex-col items-center gap-1 rounded-2xl bg-accent/90 py-2 text-accent-foreground shadow-soft focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface" data-quick-action="save">
                        <i class="ph ph-floppy-disk text-lg"></i>
                        <span>Save</span>
                    </button>
                    <button type="button" class="flex flex-col items-center gap-1 rounded-2xl bg-white/80 py-2 text-slate-600 shadow-inner focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface dark:bg-white/10 dark:text-slate-200" data-quick-action="visibility">
                        <i class="ph ph-eye text-lg"></i>
                        <span>Share</span>
                    </button>
                    <button type="button" class="flex flex-col items-center gap-1 rounded-2xl bg-white/80 py-2 text-red-500 shadow-inner focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface dark:bg-white/10" data-quick-action="delete">
                        <i class="ph ph-trash text-lg"></i>
                        <span>Delete</span>
                    </button>
                </div>
            </nav>
        </div>
    </div>
    <div id="toastContainer" class="pointer-events-none fixed right-6 top-6 z-50 flex w-full max-w-sm flex-col gap-3" aria-live="polite" aria-atomic="true"></div>
    <template id="noteListItem">
        <button class="note-item group flex w-full flex-col rounded-2xl border border-transparent bg-white/80 px-4 py-3 text-left transition-all duration-200 hover:-translate-y-0.5 hover:border-accent/40 hover:shadow-soft focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface dark:bg-white/10" type="button" data-slug="">
            <div class="flex items-center justify-between gap-3">
                <span class="note-item__title text-sm font-medium text-slate-900 group-hover:text-accent dark:text-white"></span>
                <span class="note-item__meta text-xs text-slate-400"></span>
            </div>
            <p class="note-item__preview mt-1 line-clamp-2 text-xs text-slate-500 dark:text-slate-300"></p>
        </button>
    </template>
    <?php echo Markly::renderFootAssets(['css_href' => '/public/md-editor.css', 'js_src' => '/public/md-editor.js']); ?>
    <script>window.MARKLY_BOOT = <?php echo json_encode($boot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;</script>
    <script type="module" src="/assets/js/app.js"></script>
</body>
</html>
