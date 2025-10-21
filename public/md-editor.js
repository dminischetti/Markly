const editor      = document.getElementById('editor');
const preview     = document.getElementById('preview');
const statsEl     = document.getElementById('stats');
const grid        = document.getElementById('grid');
const toggleBtn   = document.getElementById('toggle');
const formatbar   = document.getElementById('formatbar');
const previewCard = document.getElementById('previewCard');

let previewOpen = false;

const DEFAULT_MD = `# Welcome 👋
This is a **simple Markdown editor** with live preview.

- Open the preview with the eye button on the right
- The view splits on wider screens

**Bold**, *Italic*, \`Code\`
`;

const saved = localStorage.getItem('md-editor-content');
editor.value = saved ?? DEFAULT_MD;

marked.setOptions({ gfm: true, breaks: false, mangle: false, headerIds: true });

function render() {
  const raw = editor.value;

  if (previewOpen) {
    preview.innerHTML = DOMPurify.sanitize(marked.parse(raw));
  }

  const words = (raw.trim().match(/\b\w+\b/g) || []).length;
  const chars = raw.length;
  const lines = raw.split('\n').length;
  statsEl.textContent = `${words} words · ${chars} characters · ${lines} lines`;

  localStorage.setItem('md-editor-content', raw);
}

editor.addEventListener('input', render);
render();

function insertAtSelection(before, after = '') {
  const start = editor.selectionStart || 0;
  const end   = editor.selectionEnd || 0;
  const sel   = editor.value.slice(start, end);
  const pos   = start + before.length + sel.length + after.length;
  
  editor.value = editor.value.slice(0, start) + before + sel + after + editor.value.slice(end);
  editor.focus();
  editor.setSelectionRange(pos, pos);
  render();
}

function surround(prefix, suffix) {
  insertAtSelection(prefix, suffix ?? prefix);
}

function insertLine(prefix) {
  const start = editor.selectionStart || editor.value.length;
  const lineStart = editor.value.lastIndexOf('\n', Math.max(0, start - 1)) + 1;
  const pos = lineStart + prefix.length;
  
  editor.value = editor.value.slice(0, lineStart) + prefix + editor.value.slice(lineStart);
  editor.focus();
  editor.setSelectionRange(pos, pos);
  render();
}

function togglePreview() {
  previewOpen = !previewOpen;
  toggleBtn.setAttribute('aria-pressed', String(previewOpen));
  
  if (previewOpen) {
    previewCard.hidden = false;
    grid.classList.add('is-split');
    preview.innerHTML = DOMPurify.sanitize(marked.parse(editor.value));
  } else {
    grid.classList.remove('is-split');
    previewCard.hidden = true;
  }
}

toggleBtn.addEventListener('click', togglePreview);

formatbar.addEventListener('click', (e) => {
  const btn = e.target.closest('button');
  if (!btn || btn.id === 'toggle') return;
  
  switch (btn.dataset.action) {
    case 'bold':   surround('**'); break;
    case 'italic': surround('*'); break;
    case 'h1':     insertLine('# '); break;
    case 'list':   insertLine('- '); break;
    case 'link':   insertAtSelection('[', '](https://)'); break;
    case 'code':   insertAtSelection('\n```\n', '\n```\n'); break;
    case 'table':  insertAtSelection('\n| Column | Column |\n|---|---|\n| A | B |\n'); break;
  }
});

document.addEventListener('keydown', (e) => {
  const key = e.key.toLowerCase();
  if ((e.ctrlKey || e.metaKey) && key === 'b') {
    e.preventDefault();
    surround('**');
  }
  if ((e.ctrlKey || e.metaKey) && key === 'i') {
    e.preventDefault();
    surround('*');
  }
});