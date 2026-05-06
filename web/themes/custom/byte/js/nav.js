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

  // i18n labels for the burger button — read from data attrs so the twig
  // owns the strings (keeps ES/EN parity automatic).
  const burgerLabelOpen  = burger ? (burger.getAttribute('data-label-open')  || 'Open menu')  : '';
  const burgerLabelClose = burger ? (burger.getAttribute('data-label-close') || 'Close menu') : '';

  function setDrawer(open) {
    if (!burger || !drawer) return;
    burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    burger.setAttribute('aria-label', open ? burgerLabelClose : burgerLabelOpen);
    drawer.classList.toggle('is-open', open);
    drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
    // Inert prevents focus from reaching the drawer when it's hidden.
    if (open) {
      drawer.removeAttribute('inert');
    } else {
      drawer.setAttribute('inert', '');
    }
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

    // Close drawer when clicking a link inside it (but not when clicking
    // the in-drawer controls — lang-toggle, theme-toggle — which should
    // toggle state without dismissing the drawer).
    drawer.querySelectorAll('a').forEach(a => {
      if (a.closest('.nav-drawer__controls')) return;
      a.addEventListener('click', () => setDrawer(false));
    });

    // Close drawer when clicking the backdrop (anything outside drawer + burger)
    document.addEventListener('click', (e) => {
      if (!body.classList.contains('nav-drawer-open')) return;
      if (drawer.contains(e.target) || burger.contains(e.target)) return;
      setDrawer(false);
    });

    // Auto-close drawer on resize past the breakpoint where the burger
    // disappears (CSS hides .nav__burger above 899px). If the user opens
    // the drawer on mobile, then resizes the window to desktop, the
    // drawer stays as a stuck overlay with no way to dismiss it because
    // the burger that toggles it is gone. Watch viewport width and force
    // the drawer closed when we cross above the breakpoint.
    const desktopMQ = window.matchMedia('(min-width: 900px)');
    const handleViewportChange = (e) => {
      if (e.matches && body.classList.contains('nav-drawer-open')) {
        setDrawer(false);
      }
    };
    // Modern browsers prefer addEventListener on the MQ; older Safari
    // versions use addListener. Cover both.
    if (desktopMQ.addEventListener) {
      desktopMQ.addEventListener('change', handleViewportChange);
    } else {
      desktopMQ.addListener(handleViewportChange);
    }
  }

  // ─── Theme toggle ───
  // Two buttons in the DOM: the desktop one in .nav__links--desktop and
  // the mobile one inside .nav-drawer__controls. Wire both. New buttons
  // injected later (e.g. by Drupal AJAX) fall through to the document-
  // level delegated handler below.
  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem('jsa-theme', theme); } catch (e) {}
  }
  function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'dark';
    applyTheme(current === 'dark' ? 'light' : 'dark');
  }
  document.querySelectorAll('[data-theme-toggle]').forEach(btn => {
    btn.addEventListener('click', toggleTheme);
  });
})();
