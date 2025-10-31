let instance = null;

export function initEditor(options = {}) {
  if (!window.MarklyEditor) {
    throw new Error('Markly editor component not loaded');
  }
  instance = window.MarklyEditor.init({
    initialValue: options.initialValue || '',
    onChange: (value) => {
      if (typeof options.onChange === 'function') {
        options.onChange(value);
      }
    },
    onSave: () => {
      if (typeof options.onSave === 'function') {
        options.onSave();
      }
    },
    onPreviewToggle: (state) => {
      if (typeof options.onPreviewToggle === 'function') {
        options.onPreviewToggle(state);
      }
    },
    onStats: (stats) => {
      if (typeof options.onStats === 'function') {
        options.onStats(stats);
      }
    },
  });
  return instance;
}

export function getValue() {
  return instance ? instance.getValue() : '';
}

export function setValue(value) {
  if (instance) {
    instance.setValue(value || '');
  }
}

export function focusEditor() {
  if (instance) {
    instance.focus();
  }
}

export function togglePreview(force) {
  if (instance) {
    instance.togglePreview(force);
  }
}

export function isPreviewOpen() {
  return instance ? instance.isPreviewOpen() : false;
}

export function refreshEditor() {
  if (instance) {
    instance.refresh();
  }
}
