/**
 * creator/js/editor.js
 * ------------------------------------------------------------------
 * Main entry point for the WYSIWYG Template Editor page.
 * Wires together SlotManager, CanvasRenderer, PropsPanel and
 * all toolbar/sidebar interactions.
 *
 * URL params:
 *   ?edit=<template_id>   — open an existing template for editing
 *   (no param)            — start a brand-new template
 * ------------------------------------------------------------------
 */
import { SlotManager }    from './SlotManager.js';
import { CanvasRenderer } from './CanvasRenderer.js';
import { PropsPanel }     from './PropsPanel.js';
import { CreatorAPI }     from './CreatorAPI.js';
import { toast, $, promptModal } from './utils.js';

/* ---- State ---------------------------------------------------------------- */
const state = {
  templateId:  null,     // set after frame upload or when editing existing
  outputW:     0,
  outputH:     0,
  templateName:'Untitled Template',
};

/* ---- DOM refs ------------------------------------------------------------ */
const stageEl    = document.getElementById('canvas-stage');
const viewportEl = document.getElementById('canvas-viewport') || document.querySelector('.canvas-scroll-viewport');
const propsEl    = document.querySelector('.creator-props');

/* ---- Core modules -------------------------------------------------------- */
const renderer = new CanvasRenderer(stageEl, viewportEl);
const slots    = new SlotManager(stageEl, () => renderer.getScale());
const props    = new PropsPanel(propsEl);

/* ---- Wire module callbacks ----------------------------------------------- */
slots.onSelect = slot => {
  props.update(slot);
  updateToolbarState();
};
slots.onChange = () => {
  updateValidationBanner();
  updateSlotCountBadge();
};
props.onChange = values => slots.applyProps(values);

/* ---- Boot ---------------------------------------------------------------- */
async function init() {
  const editId    = window.__EDIT_ID__ || null;
  const returnUrl = new URLSearchParams(location.search).get('return') || '../creator/gallery.php';

  // Restore-on-back button
  $('#btn-back-gallery').href = returnUrl;

  // Frame upload wiring
  const frameInput = document.getElementById('frame-file-input');
  frameInput.addEventListener('change', e => {
    if (e.target.files[0]) uploadFrame(e.target.files[0]);
  });

  // Drag-drop onto the upload zone
  const dropZone = document.getElementById('upload-drop-zone');
  if (dropZone) {
    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('is-dragover'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('is-dragover'));
    dropZone.addEventListener('drop', e => {
      e.preventDefault(); dropZone.classList.remove('is-dragover');
      const file = e.dataTransfer.files[0];
      if (file) uploadFrame(file);
    });
    dropZone.addEventListener('click', () => frameInput.click());
  }

  // Load an existing template if ?edit= is set.
  if (editId) {
    await loadExistingTemplate(editId);
  }

  bindToolbar();
  bindSidebar();
  bindKeyboard();

  // Zoom refit on window resize.
  window.addEventListener('resize', () => renderer.fitToViewport());

  updateToolbarState();
  updateSlotCountBadge();
}

/* ---- Template loading ---------------------------------------------------- */
async function loadExistingTemplate(id) {
  try {
    const { templates } = await CreatorAPI.listTemplates();
    const tpl = templates.find(t => t.id === id);
    if (!tpl) { toast('Template not found.', 'error'); return; }

    state.templateId   = id;
    state.templateName = tpl.name;
    state.outputW      = tpl.output.width;
    state.outputH      = tpl.output.height;

    // Populate sidebar fields.
    $('#f-tpl-name').value   = tpl.name;
    $('#f-tpl-desc').value   = tpl.config.description || '';
    $('#f-tpl-author').value = tpl.config.author || '';
    $('#f-out-w').value      = tpl.output.width;
    $('#f-out-h').value      = tpl.output.height;
    $('#f-bg-color').value   = tpl.config.background || '#ffffff';

    // Mount frame + slots.
    renderer.setSize(tpl.output.width, tpl.output.height);
    slots.setDimensions(tpl.output.width, tpl.output.height);
    renderer.setFrame('../' + tpl.frame_url);
    slots.loadAll(tpl.config.photos || []);

    showEditor();
    document.querySelector('.creator-header__title').textContent = tpl.name;
    toast('Template loaded.', 'success');
  } catch(e) {
    toast('Could not load template: ' + e.message, 'error');
  }
}

/* ---- Frame upload -------------------------------------------------------- */
async function uploadFrame(file) {
  const uploadSection = document.getElementById('upload-section');
  const progress      = document.getElementById('upload-progress');
  if (progress) progress.textContent = 'Uploading…';

  try {
    const res = await CreatorAPI.uploadFrame(file, state.templateId || '');
    state.templateId = res.template_id;
    state.outputW    = res.width;
    state.outputH    = res.height;

    // Set the output dimension inputs to match the actual uploaded image.
    $('#f-out-w').value = res.width;
    $('#f-out-h').value = res.height;

    renderer.setSize(res.width, res.height);
    slots.setDimensions(res.width, res.height);
    renderer.setFrame('../' + res.frame_url);
    slots.clear();

    showEditor();
    toast('Frame uploaded. Place your photo slots.', 'success');
  } catch(e) {
    toast('Upload failed: ' + e.message, 'error');
    if (progress) progress.textContent = '';
  }
}

function showEditor() {
  const uploadSection  = document.getElementById('upload-section');
  const canvasViewport = document.getElementById('canvas-viewport');
  if (uploadSection)  uploadSection.style.display  = 'none';
  if (canvasViewport) canvasViewport.style.display  = '';
}

/* ---- Toolbar wiring ------------------------------------------------------ */
function bindToolbar() {
  // Add slot
  $('#btn-add-slot').addEventListener('click', () => {
    if (!state.templateId) { toast('Upload a frame first.', 'error'); return; }
    const n = slots.slots.length;
    const gap = Math.round(Math.min(state.outputW, state.outputH) * 0.05);
    slots.add(gap, gap + n * 40, Math.round(state.outputW * 0.7), Math.round(state.outputH * 0.18));
  });

  // Remove selected slot
  $('#btn-remove-slot').addEventListener('click', () => slots.removeSelected());

  // Zoom
  const zoomDisplay = document.getElementById('zoom-level');
  $('#btn-zoom-in').addEventListener('click',  () => {
    const pct = renderer.zoom(+0.1);
    if (zoomDisplay) zoomDisplay.textContent = pct + '%';
  });
  $('#btn-zoom-out').addEventListener('click', () => {
    const pct = renderer.zoom(-0.1);
    if (zoomDisplay) zoomDisplay.textContent = pct + '%';
  });
  $('#btn-zoom-fit').addEventListener('click', () => {
    renderer.fitToViewport();
    if (zoomDisplay) zoomDisplay.textContent = Math.round(renderer.getScale() * 100) + '%';
  });

  // Snap toggle
  const snapBtn = $('#btn-snap-toggle');
  snapBtn?.addEventListener('click', () => {
    snapBtn.classList.toggle('is-active');
    slots.setSnapEnabled(snapBtn.classList.contains('is-active'));
  });
  snapBtn?.classList.add('is-active'); // snap on by default

  // Alignment
  const alignMap = {
    'btn-align-left':  () => slots.alignLeft(),
    'btn-align-ch':    () => slots.alignCenterH(),
    'btn-align-right': () => slots.alignRight(),
    'btn-align-top':   () => slots.alignTop(),
    'btn-align-cv':    () => slots.alignCenterV(),
    'btn-align-bottom':() => slots.alignBottom(),
    'btn-dist-v':      () => slots.distributeVertically(),
    'btn-dist-h':      () => slots.distributeHorizontally(),
    'btn-match-w':     () => slots.matchWidth(),
    'btn-match-h':     () => slots.matchHeight(),
    'btn-forward':     () => slots.bringForward(),
    'btn-backward':    () => slots.sendBackward(),
    'btn-reset-rot':   () => slots.resetAll(),
  };
  Object.entries(alignMap).forEach(([id, fn]) => {
    document.getElementById(id)?.addEventListener('click', fn);
  });

  // Preview
  $('#btn-preview').addEventListener('click', generatePreview);

  // Save
  $('#btn-save').addEventListener('click', save);
}

function bindSidebar() {
  // Live-update the header title from the name field.
  $('#f-tpl-name')?.addEventListener('input', e => {
    state.templateName = e.target.value || 'Untitled Template';
    document.querySelector('.creator-header__title').textContent = state.templateName;
  });
  // Change frame button.
  $('#btn-change-frame')?.addEventListener('click', () => {
    document.getElementById('frame-file-input').click();
  });
}

function bindKeyboard() {
  document.addEventListener('keydown', e => {
    if (e.key === 'Delete' || e.key === 'Backspace') {
      if (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA') return;
      slots.removeSelected();
    }
  });
}

/* ---- Validation banner --------------------------------------------------- */
function updateValidationBanner() {
  const banner   = document.getElementById('validation-banner');
  const warnings = slots.validate();
  if (!banner) return;
  if (warnings.length > 0) {
    banner.textContent = '⚠ ' + warnings.join('  •  ');
    banner.classList.add('is-visible');
  } else {
    banner.classList.remove('is-visible');
  }
}

function updateSlotCountBadge() {
  const badge = document.getElementById('slot-count-badge');
  if (badge) badge.textContent = `${slots.slots.length} slot${slots.slots.length !== 1 ? 's' : ''}`;
}

function updateToolbarState() {
  const hasSelection = !!slots.selected;
  ['btn-remove-slot','btn-align-left','btn-align-ch','btn-align-right',
   'btn-align-top','btn-align-cv','btn-align-bottom','btn-forward','btn-backward']
    .forEach(id => {
      const el = document.getElementById(id);
      if (el) el.disabled = !hasSelection && !['btn-dist-v','btn-dist-h'].includes(id);
    });
}

/* ---- Preview ------------------------------------------------------------- */
async function generatePreview() {
  if (!state.templateId) { toast('No template loaded.', 'error'); return; }
  const btn = $('#btn-preview');
  btn.disabled = true;
  btn.textContent = 'Generating…';
  try {
    const res = await CreatorAPI.generatePreview(
      state.templateId,
      { width: state.outputW, height: state.outputH },
      slots.export()
    );
    // Show the preview in a modal.
    showPreviewModal('../' + res.preview_url);
    toast('Preview generated.', 'success');
  } catch(e) {
    toast('Preview failed: ' + e.message, 'error');
  } finally {
    btn.disabled = false;
    btn.textContent = '🖼 Preview';
  }
}

function showPreviewModal(url) {
  let modal = document.getElementById('preview-modal');
  if (!modal) {
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop';
    backdrop.id = 'preview-modal';
    backdrop.innerHTML = `
      <div class="modal modal--wide" style="align-items:center">
        <h2>Preview</h2>
        <img class="preview-modal-img" src="" alt="Preview">
        <div class="modal__actions">
          <button class="header-btn header-btn--primary" onclick="document.getElementById('preview-modal').classList.add('hidden')">Close</button>
        </div>
      </div>`;
    document.body.appendChild(backdrop);
    backdrop.addEventListener('click', e => { if (e.target === backdrop) backdrop.classList.add('hidden'); });
    modal = backdrop;
  }
  modal.querySelector('.preview-modal-img').src = url + '&t=' + Date.now();
  modal.classList.remove('hidden');
}

/* ---- Save ---------------------------------------------------------------- */
async function save() {
  if (!state.templateId) { toast('Upload a frame image first.', 'error'); return; }

  const name = ($('#f-tpl-name')?.value || '').trim();
  if (!name) { toast('Enter a template name.', 'error'); $('#f-tpl-name')?.focus(); return; }

  const warnings = slots.validate();
  if (warnings.length > 0 && !confirm('There are layout warnings:\n\n' + warnings.join('\n') + '\n\nSave anyway?')) return;

  const btn = $('#btn-save');
  btn.disabled = true; btn.textContent = 'Saving…';

  try {
    const res = await CreatorAPI.saveTemplate({
      template_id:  state.templateId,
      name,
      description:  $('#f-tpl-desc')?.value  || '',
      author:       $('#f-tpl-author')?.value || '',
      background:   $('#f-bg-color')?.value   || '#ffffff',
      output: {
        width:  parseInt($('#f-out-w')?.value) || state.outputW,
        height: parseInt($('#f-out-h')?.value) || state.outputH,
      },
      photos: slots.export(),
    });
    toast('Template saved!', 'success');
    // Redirect back to gallery after a short pause.
    setTimeout(() => {
      const params = new URLSearchParams(location.search);
      window.location.href = params.get('return') || '../creator/gallery.php';
    }, 1000);
  } catch(e) {
    toast('Save failed: ' + e.message, 'error');
  } finally {
    btn.disabled = false; btn.textContent = '💾 Save Template';
  }
}

init();
