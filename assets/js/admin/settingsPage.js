/**
 * assets/js/admin/settingsPage.js
 * ------------------------------------------------------------------
 * Renders the tabbed Settings UI inside admin/settings.php.
 * Talks only to the admin-protected api/*.php endpoints.
 * ------------------------------------------------------------------
 */
import { $, el, toast, apiFetch, apiPost } from '../utils.js';
import { CameraController } from '../camera.js';

const root = $('#settings-form');
const TABS = [
  { id: 'printing', icon: '🖨️', label: 'Printing' },
  { id: 'storage', icon: '💾', label: 'Photo Storage' },
  { id: 'capture', icon: '📷', label: 'Camera & Capture' },
  { id: 'templates', icon: '🖼️', label: 'Templates' },
  { id: 'security', icon: '🔒', label: 'Security' },
];

let state = null;      // response of api/settings_get.php
let cameraDevices = [];
let activeTab = (() => { try { return localStorage.getItem('pb_settings_tab'); } catch { return null; } })() || 'printing';
if (!TABS.some((t) => t.id === activeTab)) activeTab = 'printing';

/* ------------------------------------------------------------------ */
/* Loading                                                              */
/* ------------------------------------------------------------------ */
async function load() {
  state = await apiFetch('../api/settings_get.php');
  try { cameraDevices = await CameraController.listDevices(); } catch { cameraDevices = []; }
  render();
}

/* ------------------------------------------------------------------ */
/* Small UI builders                                                    */
/* ------------------------------------------------------------------ */
function switchRow(label, hint, checked, key) {
  return el('div', { class: 'switch-row' }, [
    el('div', {}, [el('div', { style: 'font-weight:600' }, label), hint ? el('div', { class: 'faint', style: 'font-size:12px;margin-top:2px' }, hint) : null]),
    el('div', {
      class: `switch${checked ? ' is-on' : ''}`, role: 'switch', tabindex: '0', dataset: { key },
      onClick: (e) => e.currentTarget.classList.toggle('is-on'),
    }, el('div', { class: 'switch__knob' })),
  ]);
}

function field(labelText, inputNode, hint, full = false) {
  return el('div', { class: `field${full ? ' field--full' : ''}` }, [
    el('label', {}, labelText), inputNode,
    hint ? el('div', { class: 'faint', style: 'font-size:12px' }, hint) : null,
  ]);
}

/** Big selectable cards (radio behaviour). Read back with choiceValue(name). */
function choiceCards(name, options, value) {
  return el('div', { class: 'choice-grid field--full', dataset: { choice: name } }, options.map((o) =>
    el('button', {
      type: 'button', class: `choice${o.value === value ? ' is-selected' : ''}`, dataset: { value: o.value },
      onClick: (e) => {
        const grid = e.currentTarget.parentElement;
        grid.querySelectorAll('.choice').forEach((c) => c.classList.remove('is-selected'));
        e.currentTarget.classList.add('is-selected');
        grid.dispatchEvent(new CustomEvent('choicechange', { bubbles: true }));
      },
    }, [
      el('span', { class: 'choice__icon' }, o.icon),
      el('span', { class: 'choice__title' }, o.title),
      el('span', { class: 'choice__desc' }, o.desc),
    ])));
}
const choiceValue = (name) => root.querySelector(`[data-choice="${name}"] .choice.is-selected`)?.dataset.value;
const isOn = (key) => !!root.querySelector(`.switch[data-key="${key}"]`)?.classList.contains('is-on');

function card(title, hint, children) {
  return el('section', { class: 'card admin-section' }, [
    el('h2', {}, title),
    hint ? el('p', { class: 'section-hint' }, hint) : null,
    el('div', { class: 'field-grid' }, children),
  ]);
}

function fmtBytes(n) {
  if (n < 1024) return `${n} B`;
  const u = ['KB', 'MB', 'GB'];
  let i = -1;
  do { n /= 1024; i++; } while (n >= 1024 && i < u.length - 1);
  return `${n.toFixed(n < 10 ? 1 : 0)} ${u[i]}`;
}

/* ------------------------------------------------------------------ */
/* Tabs                                                                 */
/* ------------------------------------------------------------------ */
function printingTab(s) {
  const printers = state.available_printers;
  const saved = s.printing.printer_name;
  const known = printers.some((p) => p.name === saved);

  const select = el('select', { id: 'f-printer-name' }, [
    el('option', { value: '' }, '— No printer (simulate only) —'),
    ...printers.map((p) => el('option', { value: p.name, ...(p.name === saved ? { selected: 'selected' } : {}) },
      `${p.name}${/offline|error|paused/i.test(p.status) ? `  (${p.status})` : ''}`)),
    ...(saved && !known ? [el('option', { value: saved, selected: 'selected' }, `${saved}  (not found on this computer)`)] : []),
  ]);

  const printerBox = el('div', { class: 'field field--full' }, [
    el('label', {}, 'Printer'),
    el('div', { class: 'printer-row' }, [
      select,
      el('button', { type: 'button', class: 'btn btn--secondary', id: 'btn-refresh-printers', onClick: refreshPrinters }, '🔄 Refresh'),
      el('button', { type: 'button', class: 'btn btn--secondary', id: 'btn-test-print', onClick: testPrint }, '🧪 Test print'),
    ]),
    el('div', { class: 'faint', style: 'font-size:12px', id: 'printer-hint' },
      printers.length
        ? `${printers.length} printer(s) found on this computer. Pick one, press “Test print”, then Save.`
        : 'No printers detected. Install the printer in your OS settings, then press Refresh.'),
  ]);

  return [
    card('Printer', 'Strips are sent here automatically after a guest accepts them.', [
      printerBox,
      el('div', { class: 'field--full' }, switchRow('Print automatically',
        'When on, each accepted strip is printed immediately. When off, strips are only saved — you can still print from the Gallery.', s.printing.auto_print, 'auto_print')),
    ]),
    card('What gets printed', 'Choose how the strip is laid out on the paper.', [
      choiceCards('layout', [
        { value: 'single', icon: '▮', title: 'One strip', desc: 'Prints the strip once, centred on the page.' },
        { value: 'two_up', icon: '▮▮', title: 'Two strips side by side', desc: 'Prints two copies on one sheet (e.g. 5R) — cut down the middle.' },
      ], s.printing.layout || 'single'),
      field('Paper size', el('select', { id: 'f-paper-size' }, PAPER_OPTIONS(s.printing.paper_size))),
      field('Copies', el('input', { type: 'number', id: 'f-copies', min: '1', max: '20', value: s.printing.copies })),
      field('Margin (mm)', el('input', { type: 'number', id: 'f-margins', min: '0', max: '50', step: '0.5', value: s.printing.margins_mm }), 'Blank border kept around the image.'),
      field('Scale (%)', el('input', { type: 'number', id: 'f-scale', min: '10', max: '200', value: s.printing.scale_percent }), '100 = fit the page. Lower to shrink if edges get cut off.'),
    ]),
  ];
}
const PAPER_OPTIONS = (current) => ['5R', '4x6', '5x7', '2x6', 'A4', 'A5', 'A6', 'Letter'].map((sz) =>
  el('option', { value: sz, ...(sz === current ? { selected: 'selected' } : {}) }, sz));

function storageTab(s) {
  const st = state.storage_stats;
  return [
    card('What to keep after each session', 'Applies to every guest session from now on.', [
      choiceCards('save_mode', [
        { value: 'both', icon: '🗂️', title: 'Raw photos + final strip', desc: 'Keeps the 4 original photos alongside the finished strip. Uses more disk space.' },
        { value: 'final_only', icon: '🎞️', title: 'Final strip only', desc: 'Raw photos are deleted as soon as the guest accepts their strip. Abandoned sessions are cleaned up automatically.' },
      ], s.storage.save_mode || 'both'),
    ]),
    card('Disk usage', 'Everything is stored as files in the sessions/ folder — no database.', [
      el('div', { class: 'stat-row field--full' }, [
        stat(st.sessions, 'sessions'),
        stat(fmtBytes(st.total_bytes), 'total size'),
        stat(st.sessions_with_raw, 'sessions with raw photos'),
        stat(fmtBytes(st.raw_bytes), 'used by raw photos'),
      ]),
      el('div', { class: 'field field--full' }, [
        el('button', {
          type: 'button', class: 'btn btn--danger', style: 'width:fit-content', id: 'btn-purge-raw',
          disabled: st.sessions_with_raw ? null : 'disabled', onClick: purgeAllRaw,
        }, `🗑️ Delete raw photos from ${st.sessions_with_raw} existing session(s)`),
        el('div', { class: 'faint', style: 'font-size:12px' }, 'Keeps every final strip. This cannot be undone.'),
      ]),
    ]),
    card('Quality', null, [
      field('Raw photo JPEG quality (1–100)', el('input', { type: 'number', id: 'f-jpeg-quality', min: '1', max: '100', value: s.storage.jpeg_quality }),
        'Applied to photos captured by the camera before upload.'),
    ]),
  ];
}
const stat = (value, label) => el('div', { class: 'stat' }, [el('div', { class: 'stat__value' }, String(value)), el('div', { class: 'stat__label' }, label)]);

function captureTab(s) {
  const cameraOptions = [el('option', { value: '' }, 'Default / system camera')].concat(
    cameraDevices.map((d, i) => el('option', { value: d.deviceId, ...(s.camera.preferred_device_label === d.label ? { selected: 'selected' } : {}) }, d.label || `Camera ${i + 1}`)));
  return [
    card('Camera', null, [
      field('Camera device', el('select', { id: 'f-camera-device' }, cameraOptions)),
      el('div', {}, switchRow('Mirror preview', 'Selfie-style flip of the live preview.', s.camera.mirror_preview, 'mirror_preview')),
    ]),
    card('Countdown & sound', null, [
      field('Countdown (seconds)', el('input', { type: 'number', id: 'f-countdown-seconds', min: '1', max: '10', value: s.countdown.seconds })),
      field('Volume (0–1)', el('input', { type: 'number', id: 'f-volume', min: '0', max: '1', step: '0.05', value: s.audio.volume })),
      el('div', { class: 'field--full' }, switchRow('Countdown beeps', 'Beep on each second of the countdown.', s.countdown.play_sound, 'countdown_play_sound')),
      field('Countdown sound file', el('input', { type: 'text', id: 'f-countdown-sound', value: s.audio.countdown_sound })),
      field('Shutter sound file', el('input', { type: 'text', id: 'f-shutter-sound', value: s.audio.shutter_sound })),
    ]),
    card('Kiosk', null, [
      field('Return to start after idle (seconds)', el('input', { type: 'number', id: 'f-idle-seconds', min: '5', max: '600', value: s.ui.idle_return_seconds })),
    ]),
  ];
}

function templatesTab(s) {
  const tpls = state.available_templates;
  return [card('Templates', 'Detected from the templates/ folder. Use the Template Creator to design new ones.', [
    field('Default template', el('select', { id: 'f-default-template' }, tpls.map((t) =>
      el('option', { value: t.id, ...(t.id === s.templates.default_template ? { selected: 'selected' } : {}) }, t.name)))),
    el('div', { class: 'field' }, [
      el('label', {}, `Installed (${tpls.length})`),
      el('div', { class: 'faint', style: 'font-size:13px' }, tpls.map((t) => t.name).join(', ') || 'None found'),
      el('div', { style: 'display:flex;gap:8px;margin-top:8px' }, [
        el('button', { type: 'button', class: 'btn btn--secondary', id: 'btn-refresh-templates', onClick: refreshTemplates }, '🔄 Rescan'),
        el('a', { class: 'btn btn--secondary', href: '../creator/gallery.php' }, 'Open Template Creator'),
      ]),
    ]),
  ])];
}

function securityTab() {
  return [card('Admin passcode', 'Used to open Settings, Gallery and the Template Creator.', [
    field('New passcode (leave blank to keep current)', el('input', { type: 'password', id: 'f-new-passcode', maxlength: '12', placeholder: '••••', autocomplete: 'new-password' })),
  ])];
}

/* ------------------------------------------------------------------ */
/* Shell                                                                */
/* ------------------------------------------------------------------ */
function render() {
  const s = state.settings;
  root.innerHTML = '';

  const nav = el('nav', { class: 'tabs' }, TABS.map((t) =>
    el('button', {
      type: 'button', class: `tab${t.id === activeTab ? ' is-active' : ''}`,
      onClick: () => { activeTab = t.id; try { localStorage.setItem('pb_settings_tab', t.id); } catch { /* optional */ } showTab(); },
    }, [el('span', {}, t.icon), el('span', {}, t.label)])));

  const builders = { printing: printingTab, storage: storageTab, capture: captureTab, templates: templatesTab, security: securityTab };
  // Render every tab (hidden ones stay in the DOM so Save reads all values).
  const panels = TABS.map((t) => el('div', { class: 'tab-panel', dataset: { tab: t.id } }, builders[t.id](s)));

  root.appendChild(nav);
  panels.forEach((p) => root.appendChild(p));
  root.appendChild(el('div', { class: 'save-bar' }, [
    el('button', { type: 'button', class: 'btn btn--secondary', onClick: () => load() }, 'Discard changes'),
    el('button', { type: 'button', class: 'btn btn--primary btn--xl', id: 'btn-save', onClick: save }, 'Save settings'),
  ]));
  showTab();
}

function showTab() {
  root.querySelectorAll('.tab').forEach((b, i) => b.classList.toggle('is-active', TABS[i].id === activeTab));
  root.querySelectorAll('.tab-panel').forEach((p) => p.classList.toggle('is-active', p.dataset.tab === activeTab));
}

/* ------------------------------------------------------------------ */
/* Actions                                                              */
/* ------------------------------------------------------------------ */
async function refreshPrinters() {
  const btn = $('#btn-refresh-printers');
  btn.disabled = true;
  try {
    const keep = $('#f-printer-name').value;
    const { printers } = await apiFetch('../api/printers_list.php');
    state.available_printers = printers;
    const sel = $('#f-printer-name');
    sel.innerHTML = '';
    sel.appendChild(el('option', { value: '' }, '— No printer (simulate only) —'));
    printers.forEach((p) => sel.appendChild(el('option', { value: p.name }, `${p.name}${/offline|error|paused/i.test(p.status) ? `  (${p.status})` : ''}`)));
    sel.value = printers.some((p) => p.name === keep) ? keep : '';
    $('#printer-hint').textContent = `${printers.length} printer(s) found.`;
    toast('Printer list refreshed.', 'success');
  } catch (e) {
    toast('Could not list printers: ' + e.message, 'error');
  } finally {
    btn.disabled = false;
  }
}

async function testPrint() {
  const printer = $('#f-printer-name').value;
  if (!printer) { toast('Select a printer first.', 'error'); return; }
  const btn = $('#btn-test-print');
  btn.disabled = true;
  btn.textContent = 'Sending…';
  try {
    const { print_result: r } = await apiPost('../api/printer_test.php', { printer });
    if (r.status === 'printed') toast(`Test page sent to ${r.printer}.`, 'success');
    else toast(`Test print ${r.status}: ${r.reason || ''}`, 'error', 6000);
  } catch (e) {
    toast('Test print failed: ' + e.message, 'error', 6000);
  } finally {
    btn.disabled = false;
    btn.textContent = '🧪 Test print';
  }
}

async function purgeAllRaw() {
  const n = state.storage_stats.sessions_with_raw;
  if (!confirm(`Permanently delete the raw photos of ${n} session(s)? Final strips are kept.`)) return;
  try {
    await apiPost('../api/session_manage.php', { action: 'purge_all_raw' });
    toast('Raw photos deleted.', 'success');
    await load();
  } catch (e) {
    toast('Failed: ' + e.message, 'error');
  }
}

async function refreshTemplates() {
  const btn = $('#btn-refresh-templates');
  btn.disabled = true;
  try {
    const res = await apiPost('../api/templates_refresh.php', {});
    toast(`Found ${res.count} template(s).`, 'success');
    await load();
  } catch (e) {
    toast('Rescan failed: ' + e.message, 'error');
    btn.disabled = false;
  }
}

async function save() {
  const num = (id, fallback, parse = parseInt) => { const v = parse($(id).value, 10); return Number.isFinite(v) ? v : fallback; };
  const settings = {
    camera: {
      preferred_device_label: $('#f-camera-device').selectedOptions[0]?.value ? $('#f-camera-device').selectedOptions[0].textContent : '',
      mirror_preview: isOn('mirror_preview'),
    },
    templates: { default_template: $('#f-default-template').value },
    printing: {
      auto_print: isOn('auto_print'),
      printer_name: $('#f-printer-name').value,
      layout: choiceValue('layout'),
      paper_size: $('#f-paper-size').value,
      copies: Math.max(1, num('#f-copies', 1)),
      margins_mm: num('#f-margins', 0, parseFloat),
      scale_percent: num('#f-scale', 100),
    },
    countdown: { seconds: Math.max(1, num('#f-countdown-seconds', 3)), play_sound: isOn('countdown_play_sound') },
    storage: { save_mode: choiceValue('save_mode'), jpeg_quality: Math.min(100, Math.max(1, num('#f-jpeg-quality', 92))) },
    audio: {
      countdown_sound: $('#f-countdown-sound').value.trim(),
      shutter_sound: $('#f-shutter-sound').value.trim(),
      volume: num('#f-volume', 0.8, parseFloat),
    },
    ui: { idle_return_seconds: num('#f-idle-seconds', 300) },
  };

  if (settings.printing.auto_print && !settings.printing.printer_name) {
    activeTab = 'printing'; showTab();
    toast('Automatic printing needs a printer — pick one first.', 'error', 5000);
    return;
  }

  const payload = { settings };
  const newPasscode = $('#f-new-passcode').value.trim();
  if (newPasscode) payload.new_passcode = newPasscode;

  const btn = $('#btn-save');
  btn.disabled = true;
  btn.textContent = 'Saving…';
  try {
    await apiPost('../api/settings_save.php', payload);
    toast('Settings saved.', 'success');
    await load();
  } catch (e) {
    toast('Could not save settings: ' + e.message, 'error');
    btn.disabled = false;
    btn.textContent = 'Save settings';
  }
}

$('#btn-logout')?.addEventListener('click', async () => {
  await apiPost('../api/admin_auth.php', { action: 'logout' });
  window.location.href = '../index.php';
});

load();
