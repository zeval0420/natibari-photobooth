/**
 * creator/js/utils.js
 * Small standalone helpers for the creator module.
 */

export const $ = (sel, root = document) => root.querySelector(sel);
export const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

export function toast(msg, type = 'default', ms = 3500) {
  const stack = document.getElementById('creator-toasts');
  if (!stack) return;
  const el = document.createElement('div');
  el.className = 'toast' + (type !== 'default' ? ' toast--' + type : '');
  el.textContent = msg;
  stack.appendChild(el);
  setTimeout(() => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(8px)';
    el.style.transition = 'opacity 200ms, transform 200ms';
    setTimeout(() => el.remove(), 220);
  }, ms);
}

export function showModal(id)  { document.getElementById(id).classList.remove('hidden'); }
export function hideModal(id)  { document.getElementById(id).classList.add('hidden'); }

export const wait = ms => new Promise(r => setTimeout(r, ms));

/** Prompt user for a string value via a modal-style prompt. Returns Promise<string|null>. */
export function promptModal(title, defaultValue = '') {
  return new Promise(resolve => {
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop';
    backdrop.innerHTML = `
      <div class="modal">
        <h2>${title}</h2>
        <div class="prop-field"><input type="text" id="prompt-input" value="${defaultValue.replace(/"/g,'&quot;')}" style="width:100%"></div>
        <div class="modal__actions">
          <button class="header-btn header-btn--ghost" id="prompt-cancel">Cancel</button>
          <button class="header-btn header-btn--primary" id="prompt-ok">OK</button>
        </div>
      </div>`;
    document.body.appendChild(backdrop);
    const input = document.getElementById('prompt-input');
    input.focus(); input.select();
    document.getElementById('prompt-ok').onclick = () => {
      const v = input.value.trim();
      backdrop.remove();
      resolve(v || null);
    };
    document.getElementById('prompt-cancel').onclick = () => { backdrop.remove(); resolve(null); };
    input.addEventListener('keydown', e => {
      if (e.key === 'Enter')  document.getElementById('prompt-ok').click();
      if (e.key === 'Escape') document.getElementById('prompt-cancel').click();
    });
  });
}

export function confirmModal(message) {
  return new Promise(resolve => {
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop';
    backdrop.innerHTML = `
      <div class="modal">
        <h2>Confirm</h2>
        <p>${message}</p>
        <div class="modal__actions">
          <button class="header-btn header-btn--ghost" id="cm-cancel">Cancel</button>
          <button class="header-btn header-btn--danger" id="cm-ok">Delete</button>
        </div>
      </div>`;
    document.body.appendChild(backdrop);
    document.getElementById('cm-ok').onclick     = () => { backdrop.remove(); resolve(true);  };
    document.getElementById('cm-cancel').onclick = () => { backdrop.remove(); resolve(false); };
  });
}
