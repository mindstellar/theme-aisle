/*
 * Aisle core: colour scheme toggle, search suggestions, phone menu. No dependencies.
 */
(function () {
  'use strict';

  var root = document.documentElement;
  var T = { products: 'Products', seeAll: 'See all results for “%s”', noMatch: 'No products match “%s”. Try a shorter word.', scheme: 'Colour scheme', system: 'System', light: 'Light', dark: 'Dark' };

  function loadText() {
    var el = document.getElementById('aisle-i18n');
    if (!el) return;
    try { Object.assign(T, JSON.parse(el.textContent || '{}')); } catch (e) { /* keep defaults */ }
  }

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined) n.textContent = text;
    return n;
  }

  function safeUrl(u) {
    try {
      var p = new URL(u, location.href);
      return p.protocol === 'http:' || p.protocol === 'https:' ? p.href : '';
    } catch (e) { return ''; }
  }

  /* One small confirmation at the bottom of the screen; a new one replaces the last. */
  window.aisleToast = function (text) {
    document.querySelectorAll('.toast').forEach(function (old) { old.remove(); });
    var t = el('div', 'toast', String(text));
    t.setAttribute('role', 'status');
    document.body.appendChild(t);
    requestAnimationFrame(function () { t.classList.add('on'); });
    setTimeout(function () { t.classList.remove('on'); setTimeout(function () { t.remove(); }, 250); }, 2200);
  };

  /* Colour scheme: system -> light -> dark */
  var ORDER = ['system', 'light', 'dark'];
  var ICON = { system: 'i-contrast', light: 'i-sun', dark: 'i-moon' };

  /* With no saved choice, start from the owner default printed on <html data-theme>. */
  function storedScheme() {
    var v = null;
    try { v = localStorage.getItem('aisle-scheme'); } catch (e) { /* private mode */ }
    if (ORDER.indexOf(v) > -1) return v;
    var owner = root.getAttribute('data-theme');
    return owner === 'light' || owner === 'dark' ? owner : 'system';
  }

  function initScheme() {
    var btn = document.querySelector('[data-scheme-toggle]');
    if (!btn) return;
    var current = storedScheme();

    function paint() {
      var label = T.scheme + ': ' + T[current];
      btn.setAttribute('aria-label', label);
      btn.setAttribute('title', label);
      btn.setAttribute('aria-pressed', current === 'system' ? 'false' : 'true');
      var use = btn.querySelector('use');
      if (use) use.setAttribute('href', '#' + ICON[current]);
    }

    btn.addEventListener('click', function () {
      current = ORDER[(ORDER.indexOf(current) + 1) % ORDER.length];
      if (current === 'system') root.removeAttribute('data-theme');
      else root.setAttribute('data-theme', current);
      try { localStorage.setItem('aisle-scheme', current); } catch (e) { /* private mode */ }
      paint();
    });
    paint();
  }

  /* Search suggestions */
  function initSuggest() {
    var form = document.querySelector('form[data-search]');
    var input = form && form.querySelector('[data-search-input]');
    var panel = form && form.querySelector('[data-suggest]');
    if (!input || !panel) return;

    var timer = 0;
    var ctrl = null;
    var active = -1;
    var seq = 0;
    panel.id = panel.id || 'aisle-suggest';
    panel.setAttribute('role', 'listbox');
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', panel.id);
    input.setAttribute('aria-expanded', 'false');

    function rows() { return panel.querySelectorAll('a[role="option"]'); }

    function close() {
      panel.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      active = -1;
    }

    function select(i) {
      var list = rows();
      list.forEach(function (a) { a.setAttribute('aria-selected', 'false'); });
      active = i;
      if (i >= 0 && list[i]) {
        list[i].setAttribute('aria-selected', 'true');
        input.setAttribute('aria-activedescendant', list[i].id);
        list[i].scrollIntoView({ block: 'nearest' });
      } else {
        input.removeAttribute('aria-activedescendant');
      }
    }

    function allUrl(q) {
      var u = new URL(form.getAttribute('action') || location.pathname, location.href);
      new FormData(form).forEach(function (v, k) { if (typeof v === 'string') u.searchParams.set(k, v); });
      u.searchParams.set('sPattern', q);
      return u.href;
    }

    function fill(tpl, q) { return tpl.replace('%s', q); }

    function render(q, listings) {
      panel.textContent = '';
      var n = 0;
      if (!listings.length) {
        panel.appendChild(el('p', 'sg-h', fill(T.noMatch, q)));
      } else {
        panel.appendChild(el('p', 'sg-h', T.products));
        listings.forEach(function (l) {
          var href = safeUrl(l.url);
          if (!href) return;
          var a = el('a');
          a.href = href;
          a.id = panel.id + '-' + n++;
          a.setAttribute('role', 'option');
          a.setAttribute('aria-selected', 'false');
          var img = el('img');
          img.alt = '';
          img.width = 40;
          img.height = 40;
          img.loading = 'lazy';
          var th = l.thumbnail ? safeUrl(l.thumbnail) : '';
          if (th) img.src = th;
          var box = el('span');
          box.appendChild(el('b', '', String(l.title || '')));
          var meta = [l.price, l.place].filter(Boolean).join(' · ');
          if (meta) box.appendChild(el('small', 'num', meta));
          a.appendChild(img);
          a.appendChild(box);
          panel.appendChild(a);
        });
        var all = el('a');
        all.href = allUrl(q);
        all.id = panel.id + '-' + n;
        all.setAttribute('role', 'option');
        all.setAttribute('aria-selected', 'false');
        all.appendChild(el('span', '', fill(T.seeAll, q)));
        panel.appendChild(all);
      }
      active = -1;
      panel.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    }

    function run() {
      var q = input.value.trim();
      if (ctrl) ctrl.abort();
      if (q.length < 3 || typeof window.aisleApi !== 'function') { close(); return; }
      ctrl = new AbortController();
      var mine = ++seq;
      window.aisleApi('search/suggest', { q: q, limit: 6 }, ctrl.signal).then(function (body) {
        if (mine !== seq || !body || !body.data) return;
        render(q, Array.isArray(body.data.listings) ? body.data.listings : []);
      });
    }

    input.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(run, 150);
    });
    input.addEventListener('focus', function () {
      if (input.value.trim().length >= 3 && panel.children.length) {
        panel.hidden = false;
        input.setAttribute('aria-expanded', 'true');
      }
    });
    input.addEventListener('keydown', function (e) {
      var list = rows();
      if (e.key === 'Escape') {
        if (!panel.hidden) { close(); e.preventDefault(); }
      } else if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && list.length) {
        e.preventDefault();
        if (panel.hidden) { panel.hidden = false; input.setAttribute('aria-expanded', 'true'); }
        var next = e.key === 'ArrowDown' ? active + 1 : active - 1;
        if (next >= list.length) next = -1;
        if (next < -1) next = list.length - 1;
        select(next);
      } else if (e.key === 'Enter' && active >= 0 && !panel.hidden && list[active]) {
        e.preventDefault();
        location.href = list[active].href;
      }
    });
    panel.addEventListener('mousedown', function (e) { e.preventDefault(); });
    form.addEventListener('focusout', function (e) {
      if (!e.relatedTarget || !form.contains(e.relatedTarget)) close();
    });
    form.addEventListener('submit', function () { clearTimeout(timer); seq++; if (ctrl) ctrl.abort(); close(); });
  }

  /* Phone menu */
  function initMenu() {
    var btn = document.querySelector('[data-menu-toggle]');
    var menu = document.querySelector('[data-menu]');
    if (!btn || !menu) return;
    var panel = menu.querySelector('.menu-panel');

    function open() {
      menu.hidden = false;
      btn.setAttribute('aria-expanded', 'true');
      root.style.overflow = 'hidden';
      var first = panel.querySelector('a, button');
      if (first) first.focus();
    }
    function close(back) {
      menu.hidden = true;
      btn.setAttribute('aria-expanded', 'false');
      root.style.overflow = '';
      if (back) btn.focus();
    }

    btn.addEventListener('click', function () { menu.hidden ? open() : close(true); });
    menu.addEventListener('click', function (e) {
      if (e.target.closest('[data-menu-close]')) close(true);
    });
    document.addEventListener('keydown', function (e) {
      if (menu.hidden) return;
      if (e.key === 'Escape') { close(true); return; }
      if (e.key !== 'Tab') return;
      var f = panel.querySelectorAll('a[href], button');
      if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
  }

  var calm = matchMedia('(prefers-reduced-motion: reduce)');

  /* The photo of the card the visitor opened keeps its name for the page change, so it carries over to the product page. */
  function initPhotoCarry() {
    var card = null;
    document.addEventListener('click', function (e) {
      card = e.target.closest ? e.target.closest('.card') : null;
    }, true);
    window.addEventListener('pageswap', function (e) {
      var img = card && !calm.matches && e.viewTransition ? card.querySelector('.ph img') : null;
      if (img) img.style.viewTransitionName = 'product-photo';
    });
    window.addEventListener('pageshow', function () {
      document.querySelectorAll('.card .ph img').forEach(function (i) { i.style.viewTransitionName = ''; });
    });
  }

  /* Adding to the cart: the product photo flies to the cart icon. */
  function initCartFly() {
    var src = null;
    var at = 0;
    document.addEventListener('click', function (e) {
      var t = e.target.closest ? e.target.closest('[data-quick-add], [data-add]') : null;
      if (!t) return;
      var box = t.closest('.card') || document.querySelector('.mainimg');
      src = box ? box.querySelector('img') : null;
      at = Date.now();
    }, true);
    document.addEventListener('aisle:cart', function () {
      var img = src;
      src = null;
      if (!img || Date.now() - at > 1500 || calm.matches || !img.animate) return;
      var badge = document.querySelector('[data-cart-count]');
      var dest = badge && (badge.closest('button, a') || badge);
      var from = img.getBoundingClientRect();
      if (!dest || !from.width) return;
      var to = dest.getBoundingClientRect();
      if (!to.width) return;
      var ghost = new Image();
      ghost.className = 'card-fly';
      ghost.alt = '';
      ghost.src = img.currentSrc || img.src;
      var size = 64;
      ghost.style.cssText = 'left:0;top:0;width:' + size + 'px;height:' + size + 'px';
      document.body.appendChild(ghost);
      var x0 = from.left + from.width / 2 - size / 2;
      var y0 = from.top + from.height / 2 - size / 2;
      var x1 = to.left + to.width / 2 - size / 2;
      var y1 = to.top + to.height / 2 - size / 2;
      var run = ghost.animate([
        { transform: 'translate(' + x0 + 'px,' + y0 + 'px) scale(1)', opacity: 1 },
        { transform: 'translate(' + x1 + 'px,' + y1 + 'px) scale(.25)', opacity: .2 }
      ], { duration: 520, easing: 'cubic-bezier(.2, .8, .2, 1)' });
      run.onfinish = run.oncancel = function () { ghost.remove(); };
    });
  }

  /* On a phone the account tabs scroll sideways; bring the current one into view. */
  function initAccountTabs() {
    var on = document.querySelector('.acct-nav a.on, .oe-account-nav [aria-current]');
    var row = on && (on.closest('.acct-nav') || on.closest('ul'));
    if (!row || row.scrollWidth <= row.clientWidth) return;
    var gap = on.offsetLeft - row.offsetLeft - 16;
    if (gap > 0) row.scrollLeft = gap;
  }

  function init() {
    loadText();
    initScheme();
    initSuggest();
    initMenu();
    initPhotoCarry();
    initCartFly();
    initAccountTabs();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
