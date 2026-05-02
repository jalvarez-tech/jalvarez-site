/**
 * @file
 * Navigation behaviors: mobile drawer toggle + theme (dark/light) toggle.
 */
(function () {
  'use strict';

  // ─── Mobile drawer ───
  const burger = document.querySelector('[data-nav-toggle]');
  const drawer = document.querySelector('[data-nav-drawer]');
  const body = document.body;

  function setDrawer(open) {
    if (!burger || !drawer) return;
    burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    drawer.classList.toggle('is-open', open);
    drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
    body.classList.toggle('nav-drawer-open', open);
  }

  if (burger && drawer) {
    burger.addEventListener('click', () => {
      const open = burger.getAttribute('aria-expanded') === 'true';
      setDrawer(!open);
    });

    // Close drawer on Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') setDrawer(false);
    });

    // Close drawer when clicking a link inside it
    drawer.querySelectorAll('a').forEach(a => {
      a.addEventListener('click', () => setDrawer(false));
    });

    // Close drawer when clicking the backdrop (anything outside drawer + burger)
    document.addEventListener('click', (e) => {
      if (!body.classList.contains('nav-drawer-open')) return;
      if (drawer.contains(e.target) || burger.contains(e.target)) return;
      setDrawer(false);
    });
  }

  // ─── Theme toggle ───
  const themeToggle = document.querySelector('[data-theme-toggle]');

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem('jsa-theme', theme); } catch (e) {}
  }

  if (themeToggle) {
    themeToggle.addEventListener('click', () => {
      const current = document.documentElement.getAttribute('data-theme') || 'dark';
      applyTheme(current === 'dark' ? 'light' : 'dark');
    });
  }
})();
