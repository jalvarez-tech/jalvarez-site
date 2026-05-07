/**
 * @file
 * Navigation behaviors: mobile drawer toggle + theme (dark/light) toggle.
 *
 * Wired through Drupal.behaviors so attach runs cleanly on initial load,
 * BigPipe placeholder replacement, and AJAX content updates. once() guards
 * every binding site to avoid double listeners.
 */
((Drupal, once) => {
  'use strict';

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem('jsa-theme', theme); } catch (e) {}
  }

  function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'dark';
    applyTheme(current === 'dark' ? 'light' : 'dark');
  }

  Drupal.behaviors.byteNav = {
    attach(context) {
      const body = document.body;

      // ─── Mobile drawer ───
      // The burger and drawer always live in the page chrome, so bind once
      // per document. The first attach (initial render) catches them; later
      // attaches see them already once'd and skip.
      once('byte-nav', '[data-nav-toggle]', context).forEach((burger) => {
        const drawer = document.querySelector('[data-nav-drawer]');
        if (!drawer) return;

        const burgerLabelOpen  = burger.getAttribute('data-label-open')  || 'Open menu';
        const burgerLabelClose = burger.getAttribute('data-label-close') || 'Close menu';

        function setDrawer(open) {
          burger.setAttribute('aria-expanded', open ? 'true' : 'false');
          burger.setAttribute('aria-label', open ? burgerLabelClose : burgerLabelOpen);
          drawer.classList.toggle('is-open', open);
          drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
          if (open) {
            drawer.removeAttribute('inert');
          } else {
            drawer.setAttribute('inert', '');
          }
          body.classList.toggle('nav-drawer-open', open);
        }

        burger.addEventListener('click', () => {
          const open = burger.getAttribute('aria-expanded') === 'true';
          setDrawer(!open);
        });

        // Dedicated in-drawer close button. The burger morphs to an X but is
        // covered by the drawer panel when open, so users have no visible
        // exit unless we render a close affordance inside the drawer itself.
        const closeBtn = drawer.querySelector('[data-nav-close]');
        if (closeBtn) {
          closeBtn.addEventListener('click', () => setDrawer(false));
        }

        // Close drawer on Escape.
        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape') setDrawer(false);
        });

        // Close drawer when clicking a link inside it (but not when clicking
        // the in-drawer controls — lang-toggle, theme-toggle — which should
        // toggle state without dismissing the drawer).
        drawer.querySelectorAll('a').forEach((a) => {
          if (a.closest('.nav-drawer__controls')) return;
          a.addEventListener('click', () => setDrawer(false));
        });

        // Close drawer when clicking the backdrop.
        document.addEventListener('click', (e) => {
          if (!body.classList.contains('nav-drawer-open')) return;
          if (drawer.contains(e.target) || burger.contains(e.target)) return;
          setDrawer(false);
        });

        // Auto-close drawer on resize past the breakpoint where the burger
        // disappears (CSS hides .nav__burger above 899px). If the user opens
        // the drawer on mobile, then resizes the window to desktop, the
        // drawer would otherwise stay as a stuck overlay with no way to
        // dismiss it because the burger that toggles it is gone.
        const desktopMQ = window.matchMedia('(min-width: 900px)');
        const handleViewportChange = (e) => {
          if (e.matches && body.classList.contains('nav-drawer-open')) {
            setDrawer(false);
          }
        };
        if (desktopMQ.addEventListener) {
          desktopMQ.addEventListener('change', handleViewportChange);
        } else {
          desktopMQ.addListener(handleViewportChange);
        }
      });

      // ─── Theme toggle ───
      // Two buttons in the DOM: desktop (.nav__links--desktop) and mobile
      // (.nav-drawer__controls). once() naturally handles both, plus any
      // future buttons injected by AJAX without re-binding existing ones.
      once('byte-theme-toggle', '[data-theme-toggle]', context).forEach((btn) => {
        btn.addEventListener('click', toggleTheme);
      });
    },
  };
})(Drupal, once);
