/**
 * creator/js/gallery.js
 * ------------------------------------------------------------------
 * Powers the Template Creator Gallery page.
 * ------------------------------------------------------------------
 */
import { CreatorAPI }                    from './CreatorAPI.js';
import { toast, promptModal, confirmModal, $ } from './utils.js';

let allTemplates = [];

async function load() {
  const grid = document.getElementById('gallery-grid');
  grid.innerHTML = '<div class="center-col" style="padding:64px"><div class="spinner"></div></div>';
  try {
    const { templates } = await CreatorAPI.listTemplates();
    allTemplates = templates;
    render(templates);
  } catch(e) {
    grid.innerHTML = `<div class="empty-state">Could not load templates: ${e.message}</div>`;
  }
}

function render(templates) {
  const grid = document.getElementById('gallery-grid');
  grid.innerHTML = '';

  if (!templates.length) {
    grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1">No templates yet. Click <strong>+ New Template</strong> to create one, or import a ZIP.</div>';
    return;
  }

  templates.forEach(tpl => {
    const card = document.createElement('div');
    card.className = 'g-card';
    const imgSrc = tpl.thumbnail_url ? '../' + tpl.thumbnail_url + '?t=' + tpl.modified_ts : '';
    const res    = tpl.output ? `${tpl.output.width}×${tpl.output.height}` : '—';
    card.innerHTML = `
      <div class="g-card__thumb">
        ${imgSrc ? `<img src="${imgSrc}" alt="${tpl.name}">` : '<span class="faint">No thumbnail</span>'}
      </div>
      <div class="g-card__body">
        <div class="g-card__name">${tpl.name}</div>
        <div class="g-card__meta">${res} · ${tpl.photos_count} slot${tpl.photos_count !== 1 ? 's' : ''}</div>
        <div class="g-card__meta" style="margin-top:2px">${tpl.modified}</div>
      </div>
      <div class="g-card__actions">
        <button class="g-card__btn" data-action="edit"      data-id="${tpl.id}">✏️ Edit</button>
        <button class="g-card__btn" data-action="preview"   data-id="${tpl.id}">🖼 Preview</button>
        <button class="g-card__btn" data-action="duplicate" data-id="${tpl.id}">⧉ Copy</button>
        <button class="g-card__btn" data-action="rename"    data-id="${tpl.id}">✎ Rename</button>
        <button class="g-card__btn" data-action="export"    data-id="${tpl.id}">⬇ Export</button>
        <button class="g-card__btn danger" data-action="delete" data-id="${tpl.id}">🗑 Delete</button>
      </div>`;
    grid.appendChild(card);
  });

  grid.addEventListener('click', async e => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const { action, id } = btn.dataset;
    await dispatch(action, id, allTemplates.find(t => t.id === id));
  });
}

async function dispatch(action, id, tpl) {
  switch(action) {
    case 'edit':
      location.href = `editor.php?edit=${encodeURIComponent(id)}`;
      break;

    case 'preview': {
      const url = tpl.preview_url || tpl.thumbnail_url;
      if (!url) { toast('No preview available.', 'error'); return; }
      showImageModal(tpl.name, '../' + url + '?t=' + Date.now());
      break;
    }

    case 'duplicate': {
      const newName = await promptModal('Duplicate as — enter new name:', tpl.name + ' Copy');
      if (!newName) return;
      try {
        await CreatorAPI.duplicateTemplate(id, newName);
        toast(`Duplicated as "${newName}"`, 'success');
        load();
      } catch(e) { toast(e.message, 'error'); }
      break;
    }

    case 'rename': {
      const newName = await promptModal('Rename template:', tpl.name);
      if (!newName || newName === tpl.name) return;
      try {
        await CreatorAPI.renameTemplate(id, newName);
        toast(`Renamed to "${newName}"`, 'success');
        load();
      } catch(e) { toast(e.message, 'error'); }
      break;
    }

    case 'export':
      window.location.href = CreatorAPI.exportUrl(id);
      break;

    case 'delete': {
      const confirmed = await confirmModal(`Permanently delete <strong>${tpl.name}</strong>? This cannot be undone.`);
      if (!confirmed) return;
      try {
        await CreatorAPI.deleteTemplate(id);
        toast(`"${tpl.name}" deleted.`);
        load();
      } catch(e) { toast(e.message, 'error'); }
      break;
    }
  }
}

function showImageModal(title, url) {
  let modal = document.getElementById('img-modal');
  if (!modal) {
    const bd = document.createElement('div');
    bd.id = 'img-modal';
    bd.className = 'modal-backdrop';
    bd.innerHTML = `
      <div class="modal modal--wide" style="align-items:center">
        <h2 id="img-modal-title"></h2>
        <img class="preview-modal-img" src="" alt="">
        <div class="modal__actions">
          <button class="header-btn header-btn--primary" id="img-modal-close">Close</button>
        </div>
      </div>`;
    document.body.appendChild(bd);
    bd.addEventListener('click', e => { if (e.target === bd) bd.classList.add('hidden'); });
    document.getElementById('img-modal-close').onclick = () => bd.classList.add('hidden');
    modal = bd;
  }
  document.getElementById('img-modal-title').textContent = title;
  modal.querySelector('.preview-modal-img').src = url;
  modal.classList.remove('hidden');
}

/* ---- Import wiring ------------------------------------------------------- */
function bindImport() {
  const importBtn   = document.getElementById('btn-import');
  const importInput = document.getElementById('import-zip-input');
  importBtn?.addEventListener('click', () => importInput?.click());
  importInput?.addEventListener('change', async e => {
    const file = e.target.files[0];
    if (!file) return;
    importInput.value = '';
    try {
      const res = await CreatorAPI.importZip(file);
      toast(`Imported "${res.name}" (${res.id})`, 'success');
      load();
    } catch(e) { toast('Import failed: ' + e.message, 'error'); }
  });
}

/* ---- Init ---------------------------------------------------------------- */
$('#btn-new-template')?.addEventListener('click', () => { location.href = 'editor.php'; });

bindImport();
load();
