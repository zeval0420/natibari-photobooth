/**
 * assets/js/utils.js
 * ------------------------------------------------------------------
 * Tiny shared helpers used across the kiosk frontend. No framework,
 * no build step — plain ES modules loaded straight by index.php.
 * ------------------------------------------------------------------
 */

/** querySelector shorthand */
export const $ = (sel, root = document) => root.querySelector(sel);
/** querySelectorAll -> real array */
export const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

/** Create an element with attributes + children in one call. */
export function el(tag, attrs = {}, children = []) {
  const node = document.createElement(tag);
  for (const [key, value] of Object.entries(attrs)) {
    if (key === 'class') node.className = value;
    else if (key === 'dataset') Object.assign(node.dataset, value);
    else if (key.startsWith('on') && typeof value === 'function') {
      node.addEventListener(key.slice(2).toLowerCase(), value);
    } else if (value !== null && value !== undefined) {
      node.setAttribute(key, value);
    }
  }
  for (const child of [].concat(children)) {
    if (child === null || child === undefined) continue;
    node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
  }
  return node;
}

/** Sleep helper for orchestrating the capture flow. */
export const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/** JSON fetch wrapper that throws a readable Error on failure. */
export async function apiFetch(url, options = {}) {
  const res = await fetch(url, {
    headers: { 'Content-Type': 'application/json' },
    ...options,
  });
  let data;
  try {
    data = await res.json();
  } catch {
    throw new Error(`Server returned a non-JSON response (HTTP ${res.status}).`);
  }
  if (!res.ok || data.success === false) {
    throw new Error(data.error || `Request failed (HTTP ${res.status}).`);
  }
  return data;
}

export function apiPost(url, body) {
  return apiFetch(url, { method: 'POST', body: JSON.stringify(body) });
}

/** Toast notifications (#toast-stack lives in index.php). */
export function toast(message, variant = 'default', duration = 3200) {
  const stack = $('#toast-stack');
  if (!stack) return;
  const node = el('div', { class: `toast ${variant !== 'default' ? 'toast--' + variant : ''}` }, message);
  stack.appendChild(node);
  setTimeout(() => {
    node.style.transition = 'opacity 200ms ease, transform 200ms ease';
    node.style.opacity = '0';
    node.style.transform = 'translateY(8px)';
    setTimeout(() => node.remove(), 220);
  }, duration);
}

/** Clamp helper used by the countdown + camera overlay math. */
export const clamp = (n, min, max) => Math.max(min, Math.min(max, n));
