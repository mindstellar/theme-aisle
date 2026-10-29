/*
 * Aisle tracking queue. Reads the #aisle-page block and the cart events, and publishes GA4-named
 * shop events to window.aisleTrack (an array) and to a document "aisle:track" event. It sends
 * nothing anywhere; an analytics plugin listens.
 */
(function () {
  'use strict';

  var win = window;
  var doc = document;
  var queue = win.aisleTrack = win.aisleTrack || [];
  var CACHE = 'aisle-track-v';
  var BOUGHT = 'aisle-purchased';
  var CART = 'aisle-cart';

  function read(key) {
    try { return win.localStorage.getItem(key); } catch (e) { return null; }
  }

  function write(key, value) {
    try { win.localStorage.setItem(key, value); } catch (e) { /* private mode: nothing is remembered */ }
  }

  function json(text, fallback) {
    try {
      var v = JSON.parse(text);
      return v === null || typeof v !== 'object' ? fallback : v;
    } catch (e) { return fallback; }
  }

  function emit(name, payload) {
    var ev = { event: name };
    Object.keys(payload || {}).forEach(function (k) { ev[k] = payload[k]; });
    queue.push(ev);
    try {
      doc.dispatchEvent(new win.CustomEvent('aisle:track', { detail: ev }));
    } catch (e) { /* a listener failing must not break the page */ }
  }

  var node = doc.getElementById('aisle-page');
  var page = node ? json(node.textContent || '', {}) : {};
  var items = Array.isArray(page.items) ? page.items : [];
  var currency = typeof page.currency === 'string' ? page.currency : '';

  /* ---------- items by variant ---------- */

  var known = json(read(CACHE) || '{}', {});

  function remember(list) {
    var changed = false;
    list.forEach(function (it) {
      if (it && it.variant_id > 0) { known[it.variant_id] = it; changed = true; }
    });
    if (!changed) return;
    var keys = Object.keys(known);
    keys.slice(0, Math.max(0, keys.length - 100)).forEach(function (k) { delete known[k]; });
    write(CACHE, JSON.stringify(known));
  }

  remember(items);

  function itemOf(variant, qty) {
    var it = known[variant];
    var out = {};
    if (it) {
      Object.keys(it).forEach(function (k) { if (k !== 'index') out[k] = it[k]; });
    } else {
      out.item_id = String(variant);
    }
    out.quantity = qty;
    return out;
  }

  function total(list) {
    return Math.round(list.reduce(function (sum, it) { return sum + (Number(it.price) || 0) * (it.quantity || 1); }, 0) * 100) / 100;
  }

  function lines() {
    var out = [];
    var stored = json(read(CART) || '[]', []);
    (Array.isArray(stored) ? stored : []).forEach(function (l) {
      if (l && Number.isInteger(l.v) && Number.isInteger(l.q) && l.q > 0) out.push({ v: l.v, q: l.q });
    });
    return out;
  }

  function cartItems(list) {
    return list.map(function (l) { return itemOf(l.v, l.q); });
  }

  function cartPayload(list) {
    var its = cartItems(list);
    return { currency: currency, value: total(its), items: its };
  }

  /* ---------- this page ---------- */

  var list = page.list && typeof page.list === 'object' ? page.list : {};

  if ((page.page === 'category' || page.page === 'search') && items.length) {
    emit('view_item_list', { item_list_id: String(list.id || ''), item_list_name: String(list.name || ''), items: items });
  }
  if (page.page === 'search' && page.search_term) {
    emit('search', { search_term: String(page.search_term) });
  }
  if (page.page === 'product' && items.length) {
    emit('view_item', { currency: currency, value: total(items), items: items });
  }
  if (page.page === 'checkout') {
    var inCart = lines();
    if (inCart.length) emit('begin_checkout', cartPayload(inCart));
  }
  if (page.page === 'order' && page.order && page.order.transaction_id) {
    var seen = json(read(BOUGHT) || '[]', []);
    seen = Array.isArray(seen) ? seen : [];
    var id = String(page.order.transaction_id);
    if (seen.indexOf(id) === -1) {
      seen.push(id);
      write(BOUGHT, JSON.stringify(seen.slice(-20)));
      emit('purchase', page.order);
    }
  }

  /* ---------- cart changes ---------- */

  var before = {};
  lines().forEach(function (l) { before[l.v] = l.q; });

  doc.addEventListener('aisle:cart', function (e) {
    var now = {};
    ((e && e.detail && e.detail.lines) || []).forEach(function (l) {
      if (l && l.v > 0 && l.q > 0) now[l.v] = l.q;
    });
    Object.keys(now).concat(Object.keys(before)).filter(function (v, i, all) { return all.indexOf(v) === i; }).forEach(function (v) {
      var delta = (now[v] || 0) - (before[v] || 0);
      if (delta === 0) return;
      var its = [itemOf(Number(v), Math.abs(delta))];
      emit(delta > 0 ? 'add_to_cart' : 'remove_from_cart', { currency: currency, value: total(its), items: its });
    });
    before = now;
  });

  /* ---------- clicks and choices ---------- */

  function closest(el, selector) {
    return el && el.closest ? el.closest(selector) : null;
  }

  doc.addEventListener('click', function (e) {
    var t = e.target;
    if (closest(t, '[data-cart-open]')) {
      var inCart = lines();
      if (inCart.length) emit('view_cart', cartPayload(inCart));
      return;
    }
    var card = closest(t, '.card');
    var link = closest(t, '.card a');
    if (!card || !link) return;
    var fav = card.querySelector('[data-fav]');
    var itemId = fav ? String(fav.getAttribute('data-fav')) : '';
    var hit = items.filter(function (it) { return it.item_id === itemId; })[0];
    if (!hit) {
      var title = card.querySelector('.t');
      hit = { item_id: itemId, item_name: title ? String(title.textContent || '').trim() : '' };
    }
    emit('select_item', { item_list_id: String(list.id || ''), item_list_name: String(list.name || ''), items: [hit] });
  });

  doc.addEventListener('change', function (e) {
    var t = e.target;
    if (!t || t.type !== 'radio' || !closest(t, 'form')) return;
    var inCart = lines();
    if (t.name === 'ship') {
      emit('add_shipping_info', Object.assign(cartPayload(inCart), { shipping_tier: String(t.value) }));
    } else if (t.name === 'pay') {
      emit('add_payment_info', Object.assign(cartPayload(inCart), { payment_type: String(t.value) }));
    }
  });

  doc.addEventListener('submit', function (e) {
    if (closest(e.target, '.news')) emit('sign_up', { method: 'newsletter' });
  });
})();
