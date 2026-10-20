/**
 * assets/js/admin/galleryPage.js
 * ------------------------------------------------------------------
 * Session gallery for admin/gallery.php: search + filter, view a
 * session (strip + raw photos), download, reprint, delete raw photos,
 * delete the session.
 * ------------------------------------------------------------------
 */
import { $, el, toast, apiFetch, apiPost } from '../utils.js';

const content = $('#gallery-content');
let all = [];
let stats = null;
let filter = 'all';
let query = '';

async function load() {
  content.innerHTML = '<div class="center-col" style="padding:64px"><div class="spinner"></div></div>';
  try {
    const res = await apiFetch('../api/sessions_list.php');
    all = res.sessions;
    stats = res.stats;
    renderShell();
  } catch (e) {
    content.innerHTML = '';
    content.appendChild(el('div', { class: 'empty-state' }, 'Could not load sessions: ' + e.message));
  }
}

const fmtBytes = (n) => (n < 1048576 ? `${Math.round(n / 1024)} KB` : `${(n / 1048576).toFixed(1)} MB`);
const fmtDate = (id) => { const [d, t = ''] = id.split('_'); return `${d}  ${t.replace(/-/g, ':')}`; };

function renderShell() {
  content.innerHTML = '';
  const printed = all.filter((s) => s.print_status === 'printed').length;
  content.appendChild(el('div', { class: 'stat-row', style: 'margin-bottom:var(--space-5)' }, [
    stat(all.length, 'sessions'), stat(printed, 'printed'),
    stat(stats.sessions_with_raw, 'with raw photos'), stat(fmtBytes(stats.total_bytes), 'disk used'),
  ]));

  const search = el('input', { type: 'search', placeholder: 'Search by date or template…', value: query });
  search.addEventListener('input', () => { query = search.value.toLowerCase(); renderGrid(); });
  const pills = el('div', { class: 'pill-group' }, [['all', 'All'], ['printed', 'Printed'], ['not_printed', 'Not printed'], ['failed', 'Failed']].map(([k, label]) =>
    el('button', {
      class: `pill${filter === k ? ' is-active' : ''}`,
      onClick: (e) => { filter = k; pills.querySelectorAll('.pill').forEach((p) => p.classList.remove('is-active')); e.currentTarget.classList.add('is-active'); renderGrid(); },
    }, label)));
  content.appendChild(el('div', { class: 'gallery-toolbar' }, [search, pills]));
  content.appendChild(el('div', { id: 'grid-holder' }));
  renderGrid();
}

const stat = (v, l) => el('div', { class: 'stat' }, [el('div', { class: 'stat__value' }, String(v)), el('div', { class: 'stat__label' }, l)]);

function matches(s) {
  const st = s.print_status || 'not_printed';
  if (filter === 'printed' && st !== 'printed') return false;
  if (filter === 'failed' && st !== 'failed') return false;
  if (filter === 'not_printed' && !['not_printed', 'skipped', 'simulated'].includes(st)) return false;
  return !query || `${s.session_id} ${s.template}`.toLowerCase().includes(query);
}

function renderGrid() {
  const holder = $('#grid-holder');
  holder.innerHTML = '';
  const list = all.filter(matches);
  if (list.length === 0) {
    holder.appendChild(el('div', { class: 'empty-state' }, all.length ? 'No sessions match.' : 'No sessions yet. Strips will appear here after guests use the booth.'));
    return;
  }
  const grid = el('div', { class: 'session-grid' });
  list.forEach((s) => grid.appendChild(buildCard(s)));
  holder.appendChild(grid);
}

function buildCard(s) {
  const status = s.print_status || 'not_printed';
  return el('div', { class: 'session-card' }, [
    el('div', { class: 'session-card__thumb', onClick: () => openLightbox(s) },
      s.strip_url ? el('img', { src: '../' + s.strip_url, alt: s.session_id, loading: 'lazy' }) : el('span', { class: 'faint' }, 'No strip')),
    el('div', { class: 'session-card__body' }, [
      el('div', { class: 'session-card__id' }, fmtDate(s.session_id)),
      el('div', { class: 'session-card__meta' }, [el('span', {}, s.template || '—'), el('span', {}, '· ' + fmtBytes(s.size_bytes || 0)), el('span', {}, s.raw_urls.length ? `· ${s.raw_urls.length} raw` : '· strip only')]),
      el('span', { class: `session-card__status status-${status}` }, status.replace('_', ' ')),
      el('div', { class: 'session-card__actions' }, [
        s.strip_url ? el('button', { class: 'btn btn--secondary', onClick: (e) => reprint(s.session_id, e.currentTarget) }, '🖨️ Print') : null,
        el('button', { class: 'btn btn--secondary', onClick: () => openLightbox(s) }, 'View'),
      ]),
    ]),
  ]);
}

function openLightbox(s) {
  const close = () => box.remove();
  const rawGrid = s.raw_urls.length
    ? el('div', { class: 'lightbox__raws' }, s.raw_urls.map((u) => el('a', { href: '../' + u, target: '_blank' }, el('img', { src: '../' + u, loading: 'lazy' }))))
    : el('p', { class: 'faint' }, 'No raw photos stored for this session.');

  const box = el('div', { class: 'lightbox', onClick: (e) => { if (e.target === box) close(); } }, el('div', { class: 'lightbox__dialog' }, [
    el('button', { class: 'lightbox__close', onClick: close, 'aria-label': 'Close' }, '×'),
    el('div', { class: 'lightbox__strip' }, s.strip_url ? el('img', { src: '../' + s.strip_url }) : el('p', { class: 'faint' }, 'No strip generated.')),
    el('div', {}, [
      el('h2', {}, fmtDate(s.session_id)),
      el('p', { class: 'faint', style: 'margin:4px 0 16px' }, `Template ${s.template || '—'} · ${fmtBytes(s.size_bytes || 0)}${s.print_info?.reason ? ' · ' + s.print_info.reason : ''}`),
      el('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, [
        s.strip_url ? el('a', { class: 'btn btn--secondary', href: '../' + s.strip_url, download: `strip_${s.session_id}.png` }, '⬇ Download strip') : null,
        s.strip_url ? el('button', { class: 'btn btn--primary', onClick: (e) => reprint(s.session_id, e.currentTarget) }, '🖨️ Print') : null,
        s.raw_urls.length && s.strip_url ? el('button', { class: 'btn btn--danger', onClick: async () => {
          if (!confirm('Delete the raw photos of this session? The strip is kept.')) return;
          await manage({ action: 'purge_raw', session_id: s.session_id }, 'Raw photos deleted.'); close();
        } }, 'Delete raw photos') : null,
        el('button', { class: 'btn btn--danger', onClick: async () => {
          if (!confirm('Delete this whole session, including the strip?')) return;
          await manage({ action: 'delete', session_id: s.session_id }, 'Session deleted.'); close();
        } }, 'Delete session'),
      ]),
      el('h3', { style: 'margin-top:24px' }, 'Raw photos'),
      rawGrid,
    ]),
  ]));
  document.body.appendChild(box);
}

async function manage(body, okMsg) {
  try {
    await apiPost('../api/session_manage.php', body);
    toast(okMsg, 'success');
    await load();
  } catch (e) {
    toast('Failed: ' + e.message, 'error');
  }
}

async function reprint(sessionId, btn) {
  const label = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Printing…';
  try {
    const { print_result: r } = await apiPost('../api/print_strip.php', { session_id: sessionId });
    if (r.status === 'printed') toast('Sent to ' + r.printer, 'success');
    else if (r.status === 'simulated') toast('No printer is set up — pick one in Settings → Printing.', 'error', 5000);
    else toast('Print failed: ' + (r.reason || 'unknown error'), 'error', 6000);
    load();
  } catch (e) {
    toast('Print failed: ' + e.message, 'error');
    btn.disabled = false;
    btn.textContent = label;
  }
}

$('#btn-logout')?.addEventListener('click', async () => {
  await apiPost('../api/admin_auth.php', { action: 'logout' });
  window.location.href = '../index.php';
});

load();
