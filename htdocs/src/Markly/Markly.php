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
  <div class="grid" id="grid">
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

        <button class="btn" id="toggle" title="Toggle preview" aria-pressed="false" aria-label="Toggle preview">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="24" height="24" aria-hidden="true">
            <path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-480H200v480Zm280-80q-82 0-146.5-44.5T240-440q29-71 93.5-115.5T480-600q82 0 146.5 44.5T720-440q-29 71-93.5 115.5T480-280Zm0-60q56 0 102-26.5t72-73.5q-26-47-72-73.5T480-540q-56 0-102 26.5T306-440q26 47 72 73.5T480-340Zm0-100Zm0 60q25 0 42.5-17.5T540-440q0-25-17.5-42.5T480-500q-25 0-42.5 17.5T420-440q0 25 17.5 42.5T480-380Z"/>
          </svg>
        </button>
      </div>

      <textarea id="editor" placeholder="Write Markdown here…"></textarea>
      <div class="stats" id="stats">0 words · 0 characters · 0 lines</div>
    </section>

    <section class="card" id="previewCard" hidden>
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
