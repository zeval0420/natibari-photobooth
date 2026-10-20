/**
 * assets/js/theme.js
 * ------------------------------------------------------------------
 * Built-in dark mode. Three sources, in priority order:
 *   1. A manual override the user/admin set on this device (localStorage)
 *   2. The "auto" setting from Settings (ui.dark_mode), which follows
 *      the OS/browser prefers-color-scheme
 *   3. Falls back to light.
 * Toggling just flips a class on <html>; every color in the app is a
 * CSS variable, so no other JS needs to know about theme at all.
 * ------------------------------------------------------------------
 */

const STORAGE_KEY = 'photobooth.themeOverride'; // "light" | "dark" | "" (no override)

function systemPrefersDark() {
  return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
}

export function applyTheme(settingValue) {
  const override = localStorage.getItem(STORAGE_KEY) || '';
  let dark;
  if (override === 'dark' || override === 'light') {
    dark = override === 'dark';
  } else if (settingValue === 'dark') {
    dark = true;
  } else if (settingValue === 'light') {
    dark = false;
  } else {
    dark = systemPrefersDark(); // "auto"
  }
  document.documentElement.classList.toggle('theme-dark', dark);
  return dark;
}

export function toggleTheme(settingValue) {
  const isDark = document.documentElement.classList.contains('theme-dark');
  localStorage.setItem(STORAGE_KEY, isDark ? 'light' : 'dark');
  return applyTheme(settingValue);
}

export function watchSystemTheme(settingValue) {
  if (!window.matchMedia) return;
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (!localStorage.getItem(STORAGE_KEY)) applyTheme(settingValue);
  });
}
