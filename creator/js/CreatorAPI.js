/**
 * creator/js/CreatorAPI.js
 * ------------------------------------------------------------------
 * Thin fetch wrappers around the creator's PHP API endpoints.
 * Mirrors the same pattern as assets/js/sessionClient.js in the main app.
 * ------------------------------------------------------------------
 */
const BASE = '../creator/api/';

async function post(endpoint, body) {
  const r = await fetch(BASE + endpoint, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  const d = await r.json();
  if (!r.ok || d.success === false) throw new Error(d.error || `Request failed (${r.status})`);
  return d;
}

async function get(endpoint, params = {}) {
  const qs = new URLSearchParams(params).toString();
  const r  = await fetch(BASE + endpoint + (qs ? '?' + qs : ''));
  const d  = await r.json();
  if (!r.ok || d.success === false) throw new Error(d.error || `Request failed (${r.status})`);
  return d;
}

export const CreatorAPI = {
  listTemplates: ()                                => get('gallery_list.php'),
  deleteTemplate: (id)                             => post('template_delete.php', { id }),
  duplicateTemplate: (source_id, new_name)         => post('template_duplicate.php', { source_id, new_name }),
  renameTemplate: (id, new_name)                   => post('template_rename.php', { id, new_name }),
  saveTemplate: (payload)                          => post('save.php', payload),
  generatePreview: (template_id, output, photos)   => post('preview_generate.php', { template_id, output, photos }),
  exportUrl: (id)                                  => BASE + 'export.php?id=' + encodeURIComponent(id),

  /** Upload a frame image via FormData; returns { template_id, frame_url, width, height }. */
  uploadFrame(file, templateId = '') {
    const fd = new FormData();
    fd.append('frame', file);
    if (templateId) fd.append('template_id', templateId);
    return fetch(BASE + 'frame_upload.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => { if (!d.success) throw new Error(d.error); return d; });
  },

  /** Upload a ZIP for import. */
  importZip(file) {
    const fd = new FormData();
    fd.append('zipfile', file);
    return fetch(BASE + 'import.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => { if (!d.success) throw new Error(d.error); return d; });
  },
};
