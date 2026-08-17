// Dismiss alert boxes
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('alert-close')) {
    e.target.closest('.alert').remove();
  }
});

// Confirm before any destructive action (deactivate, garbage, delete links)
document.addEventListener('click', function (e) {
  var el = e.target.closest('[data-confirm]');
  if (el && !confirm(el.getAttribute('data-confirm'))) {
    e.preventDefault();
  }
});

// Filter bottom-sheet (fixed IDs, one per page - see any page that builds
// a #filterModalOverlay, e.g. modules/inward/inward-list.php) - generic
// here instead of per-page inline script since every page wires it up
// identically.
(function () {
  var btn = document.getElementById('filterTriggerBtn');
  var overlay = document.getElementById('filterModalOverlay');
  var closeBtn = document.getElementById('filterModalClose');
  if (!btn || !overlay) return;

  function open() { overlay.classList.add('is-open'); document.body.style.overflow = 'hidden'; }
  function close() { overlay.classList.remove('is-open'); document.body.style.overflow = ''; }

  btn.addEventListener('click', open);
  if (closeBtn) closeBtn.addEventListener('click', close);
  overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
})();

// Mobile sidebar toggle (opened from the bottom tab bar's "More" button)
(function () {
  var toggle = document.getElementById('tabMoreBtn');
  var sidebar = document.getElementById('sidebar');
  var backdrop = document.getElementById('sidebarBackdrop');
  if (!toggle || !sidebar || !backdrop) return;

  function closeSidebar() {
    sidebar.classList.remove('is-open');
    backdrop.classList.remove('is-open');
  }

  toggle.addEventListener('click', function () {
    sidebar.classList.toggle('is-open');
    backdrop.classList.toggle('is-open');
  });
  backdrop.addEventListener('click', closeSidebar);
  sidebar.addEventListener('click', function (e) {
    if (e.target.closest('a.nav-link')) closeSidebar();
  });
})();

// Smooth sliding active-tab indicator on the mobile bottom tab bar. Snaps
// into place instantly on load (no slide-in from nowhere), then glides
// between tabs on click - navigation is briefly delayed so the slide is
// actually visible before the page unloads.
(function () {
  var bar = document.querySelector('.bottom-tabbar');
  if (!bar) return;

  var indicator = document.createElement('div');
  indicator.className = 'tab-indicator';
  bar.insertBefore(indicator, bar.firstChild);

  function place(link, animate) {
    if (!link) { indicator.style.width = '0'; return; }
    var barRect = bar.getBoundingClientRect();
    var linkRect = link.getBoundingClientRect();
    if (!animate) indicator.style.transition = 'none';
    indicator.style.width = linkRect.width + 'px';
    indicator.style.transform = 'translateX(' + (linkRect.left - barRect.left) + 'px)';
    if (!animate) {
      indicator.offsetHeight; // force reflow before re-enabling the transition
      indicator.style.transition = '';
    }
  }

  place(bar.querySelector('.tab-link.active'), false);

  bar.querySelectorAll('a.tab-link').forEach(function (link) {
    link.addEventListener('click', function (e) {
      if (link.classList.contains('active')) return;
      e.preventDefault();
      place(link, true);
      bar.querySelectorAll('.tab-link').forEach(function (l) { l.classList.remove('active'); });
      link.classList.add('active');
      document.body.classList.add('page-leaving');
      var href = link.getAttribute('href');
      setTimeout(function () { window.location.href = href; }, 180);
    });
  });

  window.addEventListener('resize', function () {
    place(bar.querySelector('.tab-link.active'), false);
  });
})();

// Same fade-out-then-navigate treatment for every other internal link on
// the page (sidebar nav, quick-action tiles, "All X" links, notification
// items, etc.) so leaving ANY page feels like the same smooth app
// transition as tapping the bottom tab bar, not just the tab bar itself.
(function () {
  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var link = e.target.closest('a');
    if (!link || !link.href) return;
    if (link.target && link.target !== '_self') return;
    if (link.hasAttribute('download')) return;
    var href = link.getAttribute('href') || '';
    if (!href || href.charAt(0) === '#' || /^(mailto|tel|javascript):/i.test(href)) return;
    if (link.origin !== window.location.origin) return;
    e.preventDefault();
    document.body.classList.add('page-leaving');
    setTimeout(function () { window.location.href = link.href; }, 160);
  });

  // A page restored from the back/forward cache can still carry the
  // "page-leaving" class it had at the moment the user navigated away -
  // without this it would silently render invisible.
  window.addEventListener('pageshow', function () {
    document.body.classList.remove('page-leaving');
  });
})();

// Icon badge + coloured text on every card heading, themed to match the
// current page's nav icon. Runs on every page automatically (no per-page
// markup needed) so this stays consistent app-wide as pages are added.
document.addEventListener('DOMContentLoaded', function () {
  var info = window.__pageHeadingIcon;
  if (!info) return;
  document.querySelectorAll('.card > h2:first-child, .card > h3:first-child').forEach(function (h) {
    h.classList.add('heading-text-' + info.color);
    if (h.querySelector('.icon-chip')) return;
    var badge = document.createElement('span');
    badge.className = 'heading-icon icon-chip icon-chip-' + info.color;
    badge.innerHTML = info.svg;
    h.insertBefore(badge, h.firstChild);
  });
});
