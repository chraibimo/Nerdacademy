/* ─── NerdAcademy — Theme Toggle ──────────────────────────────────────────────
   Persists dark/light preference to localStorage.
   ─────────────────────────────────────────────────────────────────────────── */
'use strict';

(function () {
  const KEY    = 'na_theme';
  const html   = document.documentElement;
  const btn    = document.getElementById('themeToggle');

  // Apply saved preference; default to light mode
  const saved  = localStorage.getItem(KEY);
  const prefer = saved || 'light';
  html.setAttribute('data-theme', prefer);

  if (!btn) return;

  btn.addEventListener('click', function () {
    const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem(KEY, next);

    // Re-tint the neural canvas connections if present
    if (typeof window._recolorCanvas === 'function') window._recolorCanvas(next);
  });
})();
