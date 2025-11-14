<?php
declare(strict_types=1);

namespace dminischetti\Markly;

final class Markly
{
    public static function renderHeadAssets(array $opts = []): string
    {
        [$cssHref] = self::resolveAssetUrls($opts);
        return '<link rel="stylesheet" href="' . self::esc($cssHref) . '">';
    }

    public static function renderFootAssets(array $opts = []): string
    {
        [, $jsSrc, $libs] = self::resolveAssetUrls($opts);
        $tags = '';

        if ($libs['include_libs']) {
            $tags .= '<script src="' . self::esc($libs['marked_cdn']) . '"></script>' . PHP_EOL;
            $tags .= '<script src="' . self::esc($libs['purify_cdn']) . '"></script>' . PHP_EOL;
        }
        $tags .= '<script src="' . self::esc($jsSrc) . '"></script>';

        return $tags;
    }

    public static function render(): string
    {
        return <<<'HTML'
<div class="wrap">
  <div class="grid is-split" id="grid">
    <section class="card" id="editorCard">
      <h6>Editor</h6>

      <div class="formatbar" id="formatbar" role="toolbar" aria-label="Formatting">
        <button class="btn" title="Bold (Ctrl/⌘+B)" data-action="bold"><b>B</b></button>
        <button class="btn" title="Italic (Ctrl/⌘+I)" data-action="italic"><i>I</i></button>
        <button class="btn" title="Heading" data-action="h1">H1</button>
        <button class="btn" title="List" data-action="list">• List</button>
        <button class="btn" title="Link" data-action="link">🔗</button>
        <button class="btn" title="Code block" data-action="code">{ }</button>
        <button class="btn" title="Table" data-action="table">⌗</button>

        <span class="formatbar__spacer" aria-hidden="true"></span>

        <button
          class="btn btn--icon icon-btn icon-btn--action"
          id="toggle"
          title="Hide preview"
          aria-pressed="true"
          aria-label="Toggle preview panel"
          data-tooltip="Hide preview"
          data-tooltip-open="Hide preview"
          data-tooltip-closed="Show preview"
        >
          <svg
            class="icon icon--preview-toggle"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7">
              <rect x="3" y="4" width="18" height="16" rx="2"></rect>
              <line class="icon__divider" x1="12" y1="5" x2="12" y2="19"></line>
            </g>
          </svg>
        </button>
      </div>

      <textarea id="editor" placeholder="Write Markdown here…"></textarea>
      <div class="stats" id="stats">0 words · 0 characters · 0 lines</div>
    </section>

    <section class="card" id="previewCard">
      <h6>Preview</h6>
      <article class="preview prose" id="preview"></article>
    </section>
  </div>

  <div class="footer">Tip: Content is automatically saved locally.</div>
</div>
HTML;
    }

    private static function resolveAssetUrls(array $opts): array
    {
        $libs = [
            'include_libs' => $opts['include_libs'] ?? true,
            'marked_cdn'   => $opts['marked_cdn'] ?? 'https://cdn.jsdelivr.net/npm/marked/marked.min.js',
            'purify_cdn'   => $opts['purify_cdn'] ?? 'https://cdn.jsdelivr.net/npm/dompurify@3.1.7/dist/purify.min.js',
        ];

        $cssHref = $opts['css_href'] ?? null;
        $jsSrc   = $opts['js_src'] ?? null;

        if ($cssHref === null || $jsSrc === null) {
            $publicUrl = !empty($opts['asset_base_url'])
                ? rtrim((string)$opts['asset_base_url'], '/') . '/public'
                : self::detectPublicUrl();

            if ($publicUrl !== null) {
                $cssHref ??= $publicUrl . '/md-editor.css';
                $jsSrc ??= $publicUrl . '/md-editor.js';
            }
        }

        $cssHref ??= '/vendor/cheinisch/markdown-editor/public/md-editor.css';
        $jsSrc ??= '/vendor/cheinisch/markdown-editor/public/md-editor.js';

        return [$cssHref, $jsSrc, $libs];
    }

    private static function detectPublicUrl(): ?string
    {
        $classFile = (new \ReflectionClass(self::class))->getFileName();
        if (!$classFile) {
            return null;
        }

        $publicDir = dirname(dirname($classFile)) . DIRECTORY_SEPARATOR . 'public';
        $publicPath = str_replace('\\', '/', $publicDir);
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']) : '';

        if ($docRoot !== '' && str_starts_with($publicPath, rtrim($docRoot, '/'))) {
            $relative = substr($publicPath, strlen(rtrim($docRoot, '/')));
            return ($relative === '' || $relative[0] !== '/') ? '/' . $relative : $relative;
        }

        return null;
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
