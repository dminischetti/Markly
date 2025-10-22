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
  <div class="editor-grid" id="grid" data-layout="split">
    <section class="editor-pane" id="editorPane" aria-label="Markdown editor">
      <header class="pane-header">
        <h2 class="pane-title">Editor</h2>
      </header>
      <div class="formatbar" id="formatbar" role="toolbar" aria-label="Formatting">
        <div class="formatbar__group formatbar__group--modes" role="group" aria-label="Workspace layout">
          <button class="format-btn format-btn--toggle" title="Edit mode" data-action="view-edit" aria-pressed="true">
            <i class="ph ph-pencil-line" aria-hidden="true"></i>
            <span class="sr-only">Show editor</span>
          </button>
          <button class="format-btn format-btn--toggle" title="Preview mode" data-action="view-preview" aria-pressed="true">
            <i class="ph ph-eye" aria-hidden="true"></i>
            <span class="sr-only">Show preview</span>
          </button>
          <button class="format-btn format-btn--toggle" title="Toggle split view" data-action="layout" aria-pressed="true">
            <i class="ph ph-layout" aria-hidden="true"></i>
            <span class="sr-only">Toggle split layout</span>
          </button>
        </div>
        <div class="formatbar__divider" aria-hidden="true"></div>
        <div class="formatbar__group" role="group" aria-label="Formatting shortcuts">
          <button class="format-btn" title="Bold (Ctrl/⌘+B)" data-action="bold">
            <i class="ph ph-text-b" aria-hidden="true"></i>
            <span class="sr-only">Bold</span>
          </button>
          <button class="format-btn" title="Italic (Ctrl/⌘+I)" data-action="italic">
            <i class="ph ph-text-italic" aria-hidden="true"></i>
            <span class="sr-only">Italic</span>
          </button>
          <button class="format-btn" title="Heading" data-action="h1">
            <i class="ph ph-text-h" aria-hidden="true"></i>
            <span class="sr-only">Heading</span>
          </button>
          <button class="format-btn" title="List" data-action="list">
            <i class="ph ph-list-bullets" aria-hidden="true"></i>
            <span class="sr-only">List</span>
          </button>
          <button class="format-btn" title="Link" data-action="link">
            <i class="ph ph-link" aria-hidden="true"></i>
            <span class="sr-only">Link</span>
          </button>
          <button class="format-btn" title="Code block" data-action="code">
            <i class="ph ph-code" aria-hidden="true"></i>
            <span class="sr-only">Code block</span>
          </button>
          <button class="format-btn" title="Table" data-action="table">
            <i class="ph ph-table" aria-hidden="true"></i>
            <span class="sr-only">Table</span>
          </button>
        </div>
        <div class="formatbar__spacer" aria-hidden="true"></div>
      </div>
      <textarea id="editor" placeholder="Write Markdown here…"></textarea>
    </section>
    <div class="editor-resizer" id="splitResizer" role="separator" aria-orientation="vertical" aria-label="Resize preview" x-data="resizerState()" x-on:pointerdown="activate($event)" x-on:pointerup.window="deactivate()" x-on:pointercancel.window="deactivate()" x-bind:class="{ 'is-active': active }"></div>
    <section class="preview-pane" id="previewCard" aria-label="Markdown preview">
      <header class="pane-header">
        <h2 class="pane-title">Preview</h2>
      </header>
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
