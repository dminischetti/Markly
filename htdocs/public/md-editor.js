(function () {
  const root = window;

  function $(id) {
    return document.getElementById(id);
  }

  function createStats(text) {
    const words = (text.trim().match(/\b\w+\b/g) || []).length;
    const chars = text.length;
    const lines = text === '' ? 0 : text.split(/\n/).length;
    return { words, chars, lines };
  }

  function renderMarkdown(raw) {
    if (typeof marked === 'undefined' || typeof DOMPurify === 'undefined') {
      return raw;
    }
    marked.setOptions({ gfm: true, breaks: false, mangle: false, headerIds: true });
    return DOMPurify.sanitize(marked.parse(raw));
  }

  function init(options) {
    const editor = $(options.textareaId || 'editor');
    const preview = $(options.previewId || 'preview');
    const statsEl = $(options.statsId || 'stats');
    const grid = $(options.gridId || 'grid');
    const toggleBtn = $(options.toggleId || 'toggle');
    const previewCard = $(options.previewCardId || 'previewCard');
    const formatbar = $(options.formatBarId || 'formatbar');

    if (!editor || !preview || !statsEl || !grid || !toggleBtn || !previewCard || !formatbar) {
      return null;
    }

    let previewOpen = true;
    let suppressChange = false;
    if (options.initialValue) {
      editor.value = options.initialValue;
    }

    function updateStats() {
      const stats = createStats(editor.value);
      statsEl.textContent = stats.words + ' words · ' + stats.chars + ' characters · ' + stats.lines + ' lines';
      if (typeof options.onStats === 'function') {
        options.onStats(stats);
      }
    }

    function updatePreview() {
      if (!previewOpen) {
        return;
      }
      preview.innerHTML = renderMarkdown(editor.value);
    }

    function renderAll() {
      updatePreview();
      updateStats();
      if (!suppressChange && typeof options.onChange === 'function') {
        options.onChange(editor.value);
      }
    }

    function togglePreview(force) {
      previewOpen = typeof force === 'boolean' ? force : !previewOpen;
      toggleBtn.setAttribute('aria-pressed', String(previewOpen));
      const tooltipOpen = toggleBtn.getAttribute('data-tooltip-open') || '';
      const tooltipClosed = toggleBtn.getAttribute('data-tooltip-closed') || '';
      const tooltip = previewOpen ? tooltipOpen : tooltipClosed;
      if (tooltip) {
        toggleBtn.setAttribute('data-tooltip', tooltip);
        toggleBtn.setAttribute('title', tooltip);
      }
      if (previewOpen) {
        previewCard.hidden = false;
        grid.classList.add('is-split');
        updatePreview();
      } else {
        previewCard.hidden = true;
        grid.classList.remove('is-split');
      }
      if (typeof options.onPreviewToggle === 'function') {
        options.onPreviewToggle(previewOpen);
      }
    }

    function insertAtSelection(before, after) {
      const start = editor.selectionStart || 0;
      const end = editor.selectionEnd || 0;
      const sel = editor.value.slice(start, end);
      const text = before + sel + (after !== undefined ? after : before);
      editor.setRangeText(text, start, end, 'end');
      renderAll();
    }

    function insertLine(prefix) {
      const start = editor.selectionStart || editor.value.length;
      const lineStart = editor.value.lastIndexOf('\n', Math.max(0, start - 1)) + 1;
      editor.setSelectionRange(lineStart, lineStart);
      editor.setRangeText(prefix, lineStart, lineStart, 'end');
      renderAll();
    }

    toggleBtn.addEventListener('click', function () {
      togglePreview();
    });

    formatbar.addEventListener('click', function (event) {
      const btn = event.target.closest('button');
      if (!btn || btn === toggleBtn) {
        return;
      }
      const action = btn.dataset.action;
      switch (action) {
        case 'bold':
          insertAtSelection('**');
          break;
        case 'italic':
          insertAtSelection('*');
          break;
        case 'h1':
          insertLine('# ');
          break;
        case 'list':
          insertLine('- ');
          break;
        case 'link':
          insertAtSelection('[', '](https://)');
          break;
        case 'code':
          insertAtSelection('\n```\n', '\n```\n');
          break;
        case 'table':
          insertAtSelection('\n| Column | Column |\n|---|---|\n| A | B |\n');
          break;
      }
    });

    editor.addEventListener('input', function () {
      renderAll();
    });

    editor.addEventListener('keydown', function (event) {
      const key = event.key.toLowerCase();
      if ((event.metaKey || event.ctrlKey) && key === 'b') {
        event.preventDefault();
        insertAtSelection('**');
      }
      if ((event.metaKey || event.ctrlKey) && key === 'i') {
        event.preventDefault();
        insertAtSelection('*');
      }
      if ((event.metaKey || event.ctrlKey) && key === 's') {
        if (typeof options.onSave === 'function') {
          event.preventDefault();
          options.onSave();
        }
      }
    });

    renderAll();

    return {
      getValue: function () {
        return editor.value;
      },
      setValue: function (value) {
        suppressChange = true;
        editor.value = value || '';
        renderAll();
        suppressChange = false;
      },
      focus: function () {
        editor.focus();
      },
      togglePreview: togglePreview,
      isPreviewOpen: function () {
        return previewOpen;
      },
      refresh: renderAll,
      element: editor,
    };
  }

  root.MarklyEditor = {
    init: init,
  };
})();
