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

  function normalizeBlocks(raw) {
    if (typeof raw !== 'string') {
      return '';
    }

    const normalized = raw.replace(/\r\n?/g, '\n');
    const lines = normalized.split('\n');
    const blockPattern = /^(#{1,6}\s+|[-+*]\s+|\d+\.\s+|```|~~~|>\s?|---$|___$|===|\|.*\|)/;
    const result = [];

    for (let i = 0; i < lines.length; i++) {
      const line = lines[i];
      if (
        blockPattern.test(line) &&
        result.length > 0 &&
        result[result.length - 1].trim() !== '' &&
        !(i > 0 && blockPattern.test(lines[i - 1]))
      ) {
        result.push('');
      }
      result.push(line);
    }

    return result.join('\n');
  }

  function renderMarkdown(raw) {
    if (typeof marked === 'undefined' || typeof DOMPurify === 'undefined') {
      return raw;
    }
    const prepared = normalizeBlocks(raw);
    marked.setOptions({ gfm: true, breaks: false, mangle: false, headerIds: true });
    return DOMPurify.sanitize(marked.parse(prepared));
  }

  function init(options) {
    const editor = $(options.textareaId || 'editor');
    const preview = $(options.previewId || 'preview');
    const statsEl = $(options.statsId || 'stats');
    const grid = $(options.gridId || 'grid');
    const formatbar = $(options.formatBarId || 'formatbar');
    const previewPane = $(options.previewCardId || 'previewCard');
    const editorPane = $(options.editorPaneId || 'editorPane');
    const resizer = $(options.resizerId || 'splitResizer');

    if (!editor || !preview || !statsEl || !grid || !previewPane || !formatbar || !editorPane) {
      return null;
    }

    const viewEditBtn = formatbar.querySelector('[data-action="view-edit"]');
    const viewPreviewBtn = formatbar.querySelector('[data-action="view-preview"]');
    const layoutBtn = formatbar.querySelector('[data-action="layout"]');
    const mediaQuery = window.matchMedia('(max-width: 900px)');

    let previewOpen = true;
    let layoutMode = mediaQuery.matches ? 'single' : 'split';
    let userLayoutPreference = null;
    let splitRatio = 0.5;
    let suppressChange = false;
    let resizing = false;
    let resizeStart = { x: 0, width: 0, total: 0, pointer: null };

    if (options.initialValue) {
      editor.value = options.initialValue;
    }

    function updateStats() {
      if (!statsEl) {
        return;
      }
      const stats = createStats(editor.value);
      statsEl.textContent = stats.words + ' words · ' + stats.chars + ' characters · ' + stats.lines + ' lines';
      if (typeof options.onStats === 'function') {
        options.onStats(stats);
      }
    }

    function renderPreview() {
      preview.innerHTML = renderMarkdown(editor.value);
    }

    function applySplitRatio() {
      if (layoutMode !== 'split') {
        editorPane.style.flexBasis = '';
        previewPane.style.flexBasis = '';
        return;
      }
      const editorPercent = Math.min(0.75, Math.max(0.25, splitRatio));
      splitRatio = editorPercent;
      const previewPercent = 1 - editorPercent;
      editorPane.style.flexBasis = (editorPercent * 100).toFixed(2) + '%';
      previewPane.style.flexBasis = (previewPercent * 100).toFixed(2) + '%';
    }

    function applyViewState() {
      const isSplit = layoutMode === 'split';
      if (isSplit) {
        previewOpen = true;
      }

      grid.setAttribute('data-layout', layoutMode);
      grid.setAttribute('data-view', previewOpen ? 'preview' : 'edit');

      const hideEditor = layoutMode === 'single' && previewOpen;
      const hidePreview = layoutMode === 'single' && !previewOpen;

      editorPane.classList.toggle('is-hidden', hideEditor);
      previewPane.classList.toggle('is-hidden', hidePreview);

      editorPane.hidden = hideEditor;
      previewPane.hidden = hidePreview;
      editorPane.setAttribute('aria-hidden', hideEditor ? 'true' : 'false');
      previewPane.setAttribute('aria-hidden', hidePreview ? 'true' : 'false');

      if (viewEditBtn) {
        const pressed = isSplit || !previewOpen;
        viewEditBtn.setAttribute('aria-pressed', pressed ? 'true' : 'false');
      }
      if (viewPreviewBtn) {
        viewPreviewBtn.setAttribute('aria-pressed', previewOpen ? 'true' : 'false');
      }
      if (layoutBtn) {
        layoutBtn.setAttribute('aria-pressed', layoutMode === 'split' ? 'true' : 'false');
        layoutBtn.setAttribute('title', layoutMode === 'split' ? 'Use single view' : 'Use split view');
      }

      if (layoutMode === 'split') {
        renderPreview();
      } else if (previewOpen) {
        renderPreview();
      }

      if (typeof options.onPreviewToggle === 'function') {
        options.onPreviewToggle(layoutMode === 'split' || previewOpen);
      }
    }

    function setLayout(mode, fromUser) {
      const desired = mode === 'single' ? 'single' : 'split';
      if (desired === layoutMode && !fromUser) {
        return;
      }
      const previous = layoutMode;
      layoutMode = desired;
      if (fromUser) {
        userLayoutPreference = desired;
      }
      if (desired === 'single' && previous !== 'single') {
        previewOpen = false;
      }
      applySplitRatio();
      applyViewState();
    }

    function setActivePane(pane) {
      if (layoutMode === 'split') {
        previewOpen = true;
      } else {
        previewOpen = pane === 'preview';
      }
      applyViewState();
    }

    function endResize(event) {
      if (!resizing) {
        return;
      }
      resizing = false;
      if (resizer) {
        resizer.classList.remove('is-active');
        if (resizeStart.pointer !== null && resizer.releasePointerCapture) {
          resizer.releasePointerCapture(resizeStart.pointer);
        }
      }
    }

    function renderAll() {
      if (layoutMode === 'split' || previewOpen) {
        renderPreview();
      }
      updateStats();
      if (!suppressChange && typeof options.onChange === 'function') {
        options.onChange(editor.value);
      }
    }

    function performUndo() {
      editor.focus();
      try {
        document.execCommand('undo');
      } catch (err) {
        // ignore unsupported command
      }
      renderAll();
    }

    function performRedo() {
      editor.focus();
      try {
        document.execCommand('redo');
      } catch (err) {
        // ignore unsupported command
      }
      renderAll();
    }

    function togglePreview(force) {
      if (layoutMode === 'split') {
        if (typeof force === 'boolean') {
          if (!force) {
            setLayout('single', true);
            setActivePane('edit');
            return;
          }
          setLayout('split');
          return;
        }
        setLayout('single', true);
        setActivePane('edit');
        return;
      }
      const next = typeof force === 'boolean' ? force : !previewOpen;
      setActivePane(next ? 'preview' : 'edit');
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

    formatbar.addEventListener('click', function (event) {
      const btn = event.target.closest('button');
      if (!btn) {
        return;
      }
      const action = btn.dataset.action;
      switch (action) {
        case 'view-edit':
          setActivePane('edit');
          return;
        case 'view-preview':
          setActivePane('preview');
          return;
        case 'layout':
          setLayout(layoutMode === 'split' ? 'single' : 'split', true);
          if (layoutMode === 'single' && !previewOpen) {
            setActivePane('edit');
          }
          return;
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

    if (resizer) {
      resizer.addEventListener('pointerdown', function (event) {
        if (layoutMode !== 'split') {
          return;
        }
        resizing = true;
        resizeStart = {
          x: event.clientX,
          width: editorPane.getBoundingClientRect().width,
          total: grid.getBoundingClientRect().width,
          pointer: event.pointerId,
        };
        resizer.classList.add('is-active');
        if (resizer.setPointerCapture) {
          resizer.setPointerCapture(event.pointerId);
        }
      });

      resizer.addEventListener('pointermove', function (event) {
        if (!resizing) {
          return;
        }
        const delta = event.clientX - resizeStart.x;
        if (resizeStart.total <= 0) {
          return;
        }
        let nextRatio = (resizeStart.width + delta) / resizeStart.total;
        nextRatio = Math.min(0.75, Math.max(0.25, nextRatio));
        splitRatio = nextRatio;
        applySplitRatio();
      });

      resizer.addEventListener('pointerup', endResize);
      resizer.addEventListener('pointercancel', endResize);
    }

    function handleMediaChange(event) {
      if (userLayoutPreference) {
        return;
      }
      const next = event.matches ? 'single' : 'split';
      if (layoutMode !== next) {
        setLayout(next);
      }
    }

    if (mediaQuery.addEventListener) {
      mediaQuery.addEventListener('change', handleMediaChange);
    } else if (mediaQuery.addListener) {
      mediaQuery.addListener(handleMediaChange);
    }

    window.addEventListener('resize', function () {
      if (!resizing) {
        applySplitRatio();
      }
    });

    setLayout(layoutMode);
    applySplitRatio();
    applyViewState();
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
        return layoutMode === 'split' || previewOpen;
      },
      refresh: renderAll,
      undo: performUndo,
      redo: performRedo,
      element: editor,
    };
  }

  root.MarklyEditor = {
    init: init,
  };
})();
