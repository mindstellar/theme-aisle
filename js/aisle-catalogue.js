/*
 * Aisle catalogue: sort, filter form, phone filter sheet, Show more. No dependencies.
 * Works on top of real pages and a plain GET form, so the page still works without it.
 */
(function () {
  'use strict';

  var T = { show: 'Show %d products', showNone: 'No products', failed: 'Could not load more.' };
  try {
    var t = document.getElementById('aisle-cat-i18n');
    if (t) Object.assign(T, JSON.parse(t.textContent || '{}'));
  } catch (e) { /* keep defaults */ }

  var form = document.getElementById('aisle-filters');
  var sheet = document.querySelector('[data-sheet]');
  var scrim = document.querySelector('[data-scrim]');
  var home = document.querySelector('[data-filters-home]');
  var opener = null;

  /* The app container clips fixed children, so the sheet lives on <body> */
  if (sheet) document.body.appendChild(sheet);
  if (scrim) document.body.appendChild(scrim);

  function inSheet() { return !!(sheet && sheet.classList.contains('on')); }

  /* Sort */
  var sort = document.querySelector('[data-sort]');
  if (sort) sort.addEventListener('change', function () { location.href = sort.value; });

  /* Filter form: submit on change on the desktop; the sheet has its own button */
  if (form) {
    var apply = form.querySelector('[data-apply]');
    if (apply) apply.hidden = true;

    form.addEventListener('submit', function () {
      /* Core's friendly URLs drop list parameters, so the options go as one plain value */
      var packed = {};
      form.querySelectorAll('input[name^="opt["]:checked').forEach(function (c) {
        var m = /^opt\[([^\]]+)\]\[\]$/.exec(c.name);
        if (m) (packed[m[1]] = packed[m[1]] || []).push(c.value);
        c.disabled = true;
      });
      var old = form.querySelector('input[name="opts"]');
      if (old) old.remove();
      var parts = Object.keys(packed).map(function (k) { return k + ':' + packed[k].join(','); });
      if (parts.length) {
        var h = document.createElement('input');
        h.type = 'hidden';
        h.name = 'opts';
        h.value = parts.join(';');
        form.appendChild(h);
      }
      Array.prototype.forEach.call(form.elements, function (f) {
        if (f.name && f.type !== 'hidden' && f.type !== 'submit' && f.type !== 'checkbox' && f.type !== 'radio' && f.value === '') f.disabled = true;
        if (f.type === 'radio' && f.value === '') f.disabled = true;
      });
    });

    form.addEventListener('change', function () {
      if (inSheet()) updateCount();
      else form.requestSubmit();
    });
  }

  /* Phone sheet: the same form moves in and back out */
  function pending() {
    var q = { limit: 1, count: 'true' };
    if (sheet.dataset.category) q.category = sheet.dataset.category;
    if (sheet.dataset.q) q.q = sheet.dataset.q;
    var fd = new FormData(form);
    var n = 0;
    fd.forEach(function (v, k) {
      v = String(v);
      if (v === '') return;
      if (k === 'sPriceMin' && /^\d+$/.test(v)) q.price_min = v;
      else if (k === 'sPriceMax' && /^\d+$/.test(v)) q.price_max = v;
      else if (k === 'stock' && v === '1') q.in_stock = 'true';
      else if (k === 'sale' && v === '1') q.on_sale = 'true';
      else {
        var m = /^meta\[(\d+)\]\[\]$/.exec(k);
        var o = /^opt\[([^\]]{1,30})\]\[\]$/.exec(k);
        if (m) q['custom_field[' + m[1] + '][' + (n++) + ']'] = v;
        else if (o) q['option[' + o[1] + '][' + (n++) + ']'] = v;
      }
    });
    return q;
  }

  var countCtl = null;
  function updateCount() {
    var btn = sheet.querySelector('[data-sheet-apply]');
    if (!btn || typeof window.aisleApi !== 'function') return;
    if (countCtl) countCtl.abort();
    countCtl = new AbortController();
    window.aisleApi('listings', pending(), countCtl.signal).then(function (body) {
      if (!body || !body.meta || typeof body.meta.total !== 'number') return;
      btn.textContent = body.meta.total > 0 ? T.show.replace('%d', body.meta.total) : T.showNone;
    });
  }

  function openSheet(e) {
    if (!sheet || !form) return;
    opener = e && e.currentTarget;
    sheet.querySelector('[data-sheet-body]').appendChild(form);
    sheet.classList.add('on');
    sheet.setAttribute('aria-hidden', 'false');
    if (scrim) scrim.classList.add('on');
    document.documentElement.classList.add('sheet-open');
    var close = sheet.querySelector('[data-sheet-close]');
    if (close) close.focus();
    updateCount();
  }

  function closeSheet() {
    if (!inSheet()) return;
    sheet.classList.remove('on');
    sheet.setAttribute('aria-hidden', 'true');
    if (scrim) scrim.classList.remove('on');
    document.documentElement.classList.remove('sheet-open');
    if (home && form) home.appendChild(form);
    if (opener) opener.focus();
  }

  document.querySelectorAll('[data-sheet-open]').forEach(function (b) { b.addEventListener('click', openSheet); });
  document.querySelectorAll('[data-sheet-close]').forEach(function (b) { b.addEventListener('click', closeSheet); });
  if (scrim) scrim.addEventListener('click', closeSheet);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSheet();
    if (e.key === 'Tab' && inSheet()) {
      var f = sheet.querySelectorAll('a[href], button, input, select, textarea');
      var vis = Array.prototype.filter.call(f, function (n) { return !n.disabled && n.offsetParent !== null; });
      if (!vis.length) return;
      var first = vis[0], last = vis[vis.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });
  window.addEventListener('resize', function () { if (inSheet() && window.innerWidth > 980) closeSheet(); });

  function status(grid) {
    var live = document.getElementById('aisle-more-status');
    if (!live) {
      live = document.createElement('p');
      live.id = 'aisle-more-status';
      live.className = 'sr';
      live.setAttribute('role', 'status');
      grid.parentNode.insertBefore(live, grid.nextSibling);
    }
    return live;
  }

  /* The live region must exist before it changes, or screen readers skip it */
  var startGrid = document.querySelector('[data-grid]');
  if (startGrid && document.querySelector('[data-loadmore]')) status(startGrid);

  /* Show more: fetch the next real page and append its cards */
  var busy = false;
  var pushed = false;
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('[data-loadmore]') : null;
    if (!a || busy || e.metaKey || e.ctrlKey || e.shiftKey || e.button) return;
    e.preventDefault();
    busy = true;
    a.setAttribute('aria-busy', 'true');
    var href = a.href;
    fetch(href, { credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw new Error('x'); return r.text(); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var grid = document.querySelector('[data-grid]');
        var cards = doc.querySelectorAll('[data-grid] > .card');
        var more = document.querySelector('[data-more]');
        var next = doc.querySelector('[data-more]');
        if (!grid || !more || !next || !cards.length) throw new Error('x');
        var first = null;
        cards.forEach(function (c) {
          var n = grid.appendChild(document.importNode(c, true));
          if (!first) first = n;
        });
        more.replaceChildren.apply(more, Array.prototype.map.call(next.childNodes, function (n) { return document.importNode(n, true); }));
        history.pushState({}, '', href);
        pushed = true;
        /* The clicked link is gone: move focus to the first new card and announce the new count */
        var shown = more.querySelector('[data-shown]');
        if (shown) status(grid).textContent = shown.textContent;
        var link = first && first.querySelector('a[href]');
        if (link) link.focus();
      })
      .catch(function () { location.href = href; })
      .then(function () { busy = false; });
  });
  window.addEventListener('popstate', function () { if (pushed) location.reload(); });
})();
