/*
 * Aisle wishlist. Signed out, the list lives in localStorage. Signed in, the account is the list:
 * each page load copies it into localStorage, and changes go to the API with the page token.
 */
(function () {
  'use strict';

  var KEY = 'aisle-wish';
  var UKEY = 'aisle-wish-user';
  var MAX = 200;
  var T = { saved: 'Saved to your wishlist', removed: 'Removed from your wishlist', failed: 'Could not update your wishlist', save: 'Save', savedLabel: 'Saved' };

  try {
    var raw = document.getElementById('aisle-wish-i18n');
    if (raw) Object.assign(T, JSON.parse(raw.textContent || '{}'));
  } catch (e) { /* keep defaults */ }

  function clean(list) {
    var seen = {};
    var out = [];
    (Array.isArray(list) ? list : []).forEach(function (v) {
      var n = typeof v === 'number' ? v : (typeof v === 'string' && /^\d{1,10}$/.test(v) ? parseInt(v, 10) : 0);
      if (Number.isInteger(n) && n > 0 && !seen[n]) { seen[n] = true; out.push(n); }
    });
    return out.slice(0, MAX);
  }

  function read(key) {
    try { return localStorage.getItem(key); } catch (e) { return null; }
  }

  function write(key, value) {
    try { localStorage.setItem(key, value); } catch (e) { /* private mode: the list lasts this page only */ }
  }

  var mem = null;

  function ids() {
    if (mem) return mem;
    var parsed = [];
    try { parsed = JSON.parse(read(KEY) || '[]'); } catch (e) { /* bad value */ }
    mem = clean(parsed);
    return mem;
  }

  function save(list) {
    mem = clean(list);
    write(KEY, JSON.stringify(mem));
    paint();
  }

  var meta = document.querySelector('meta[name="aisle-user"]');
  var user = meta ? (meta.getAttribute('content') || '') : '';
  if (!/^\d+$/.test(user)) user = '';

  function apiConfig() {
    var m = document.querySelector('meta[name="shopclass-api"]');
    if (!m) return null;
    try {
      var d = JSON.parse(m.getAttribute('content') || '');
      return d && d.url ? d : null;
    } catch (e) { return null; }
  }

  function call(method, path, body) {
    var c = apiConfig();
    if (!c || !c.token) return Promise.reject(new Error('no token'));
    var headers = { Accept: 'application/json' };
    headers[c.header] = c.token;
    var opts = { method: method, headers: headers, credentials: 'same-origin', keepalive: true };
    if (body !== undefined) {
      headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    return fetch(String(c.url).replace(/\/+$/, '') + '/' + path, opts).then(function (r) {
      if (!r.ok) throw new Error('status ' + r.status);
      return r.status === 204 ? null : r.json();
    });
  }

  function toast(text) {
    document.querySelectorAll('.toast').forEach(function (old) { old.remove(); });
    var t = document.createElement('div');
    t.className = 'toast';
    t.setAttribute('role', 'status');
    t.textContent = text;
    document.body.appendChild(t);
    requestAnimationFrame(function () { t.classList.add('on'); });
    setTimeout(function () { t.classList.remove('on'); setTimeout(function () { t.remove(); }, 250); }, 2200);
  }

  function paint() {
    var list = ids();
    var set = {};
    list.forEach(function (n) { set[n] = true; });
    document.querySelectorAll('[data-fav]').forEach(function (b) {
      var on = !!set[parseInt(b.getAttribute('data-fav'), 10)];
      if (b.getAttribute('aria-pressed') !== String(on)) b.setAttribute('aria-pressed', String(on));
      var label = b.querySelector('[data-fav-label]');
      var text = on ? T.savedLabel : T.save;
      if (label && label.textContent !== text) label.textContent = text;
    });
    document.querySelectorAll('[data-wishlist-count]').forEach(function (c) {
      if (c.textContent !== String(list.length)) c.textContent = String(list.length);
      if (c.hidden !== (list.length === 0)) c.hidden = list.length === 0;
    });
  }

  var ready = Promise.resolve();
  /* Taps made while the account list loads, replayed on top of it. */
  var edits = null;

  function replay(list) {
    (edits || []).forEach(function (e) {
      list = list.filter(function (n) { return n !== e.id; });
      if (e.on) list = [e.id].concat(list);
    });
    edits = null;
    return list;
  }

  function accountIds() {
    return call('GET', 'account/wishlist').then(function (b) { return clean(b && b.data && b.data.ids); });
  }

  function sync() {
    var last = read(UKEY) || '';
    if (user === '' && last !== '') {
      /* Signed out: do not leave the last person's list behind. */
      write(KEY, '[]');
      write(UKEY, '');
      mem = [];
      return;
    }
    if (user === '') return;
    edits = [];
    if (user === last) {
      /* The account is the list: refresh the local copy from it. */
      ready = accountIds().then(function (list) { save(replay(list)); }).catch(function () { edits = null; });
      return;
    }
    /* First sign-in on this browser: add the guest's saves to the account, once. Another user's
       leftover list is dropped. The pending mark makes a sign-out clear the list even if this fails. */
    var guest = last === '' || last === '~' + user ? ids() : [];
    write(UKEY, '~' + user);
    ready = accountIds().then(function (account) {
      if (guest.length === 0) return account;
      var union = clean(guest.concat(account));
      return call('PUT', 'account/wishlist', { ids: union }).then(accountIds).catch(function () { return account; });
    }).then(function (merged) {
      save(replay(merged));
      write(UKEY, user);
    }).catch(function () { edits = null; /* try again on the next page */ });
  }

  function toggle(id, btn) {
    var list = ids();
    var had = list.indexOf(id) > -1;
    save(had ? list.filter(function (n) { return n !== id; }) : [id].concat(list));
    toast(had ? T.removed : T.saved);
    leave(btn, had);
    if (user === '') return;
    if (edits) edits.push({ id: id, on: !had });
    ready.then(function () {
      return had ? call('DELETE', 'account/wishlist/' + id) : call('POST', 'account/wishlist/' + id);
    }).catch(function () {
      save(had ? [id].concat(ids()) : ids().filter(function (n) { return n !== id; }));
      toast(T.failed);
    });
  }

  /* On the wishlist page, a removed heart takes its card away. */
  function leave(btn, removed) {
    var grid = btn && btn.closest ? btn.closest('[data-wishlist-grid]') : null;
    if (!grid || !removed) return;
    var card = btn.closest('.card');
    if (card) card.remove();
    var empty = document.querySelector('[data-wishlist-empty]');
    if (empty) empty.hidden = grid.querySelector('.card') !== null;
  }

  function productHeart() {
    var info = document.querySelector('.info[data-listing]');
    var host = info ? info.querySelector('.share') : null;
    var id = info ? parseInt(info.getAttribute('data-listing'), 10) : 0;
    if (!host || !(id > 0) || host.querySelector('[data-fav]')) return;
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'btn btn-ghost';
    b.setAttribute('data-fav', String(id));
    b.setAttribute('aria-pressed', 'false');
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'ico sm');
    svg.setAttribute('aria-hidden', 'true');
    var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    use.setAttribute('href', '#i-heart');
    svg.appendChild(use);
    var label = document.createElement('span');
    label.setAttribute('data-fav-label', '');
    label.textContent = T.save;
    b.appendChild(svg);
    b.appendChild(label);
    host.appendChild(b);
  }

  /* Signed out, the wishlist page asks for the saved ids and shows the cards it gets back.
     Signed in, the page already shows the account list. */
  function fillPage() {
    var page = document.querySelector('[data-wishlist-page]');
    if (!page || user !== '') return;
    var shown = (page.getAttribute('data-ids') || '');
    var all = ids().join(',');
    var want = ids().slice(0, 50).join(',');
    var grid = page.querySelector('[data-wishlist-grid]');
    var empty = page.querySelector('[data-wishlist-empty]');
    if (!grid || shown === all) return;
    if (want === '') {
      grid.textContent = '';
      if (empty) empty.hidden = false;
      return;
    }
    var url = new URL(page.getAttribute('data-url') || location.href, location.href);
    url.searchParams.set('wish', want);
    fetch(url.toString(), { credentials: 'same-origin' }).then(function (r) {
      if (!r.ok) throw new Error('x');
      return r.text();
    }).then(function (html) {
      var doc = new DOMParser().parseFromString(html, 'text/html');
      var cards = doc.querySelectorAll('[data-wishlist-grid] > .card');
      grid.textContent = '';
      cards.forEach(function (c) { grid.appendChild(document.importNode(c, true)); });
      if (empty) empty.hidden = cards.length > 0;
      page.setAttribute('data-ids', want);
      paint();
    }).catch(function () { /* leave the page as it is */ });
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-fav]') : null;
    if (!btn) return;
    var id = parseInt(btn.getAttribute('data-fav'), 10);
    if (!(id > 0)) return;
    e.preventDefault();
    toggle(id, btn);
  });

  var pending = false;
  new MutationObserver(function () {
    if (pending) return;
    pending = true;
    requestAnimationFrame(function () { pending = false; paint(); });
  }).observe(document.body, { childList: true, subtree: true });

  sync();
  productHeart();
  paint();
  ready.then(function () { paint(); fillPage(); });
})();
