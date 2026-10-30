/*
 * Aisle cart. The cart lives in localStorage as [{v: variantId, q: quantity}]; signed in, it is
 * mirrored to the account (core's account/cart). The drawer paints only what GET cart/quote answers:
 * the browser sends ids and quantities, never a price. It also keeps the unit price each line had
 * when first quoted (PKEY), so a later price change shows as a notice; the order always uses the
 * server's current price.
 */
(function () {
  'use strict';

  var KEY = 'aisle-cart';
  var UKEY = 'aisle-cart-user';
  var PKEY = 'aisle-cart-price';
  var CKEY = 'aisle-cart-coupon';
  var MAXLINES = 50;
  var MAXQTY = 20;
  var limitsAsked = false;
  var T = {
    added: 'Added', add: 'Add to cart', failed: 'Could not update your cart', sold: 'Sold out', unbuyable: 'This item cannot be bought right now and was removed from your cart.',
    left: 'Only %d left', less: 'Less', more: 'More', remove: 'Remove',
    price_changed: 'The price of %1$s changed from %2$s to %3$s.', ok: 'OK',
    free: 'You get free delivery', more_for: 'Add %s more for free delivery', items: '(%d)', checkout: 'Checkout'
  };

  try {
    var raw = document.getElementById('aisle-cart-i18n');
    if (raw) Object.assign(T, JSON.parse(raw.textContent || '{}'));
  } catch (e) { /* keep defaults */ }

  /* ---------- store ---------- */

  function toInt(n) {
    if (typeof n === 'number') return Number.isInteger(n) ? n : 0;
    return typeof n === 'string' && /^\d{1,10}$/.test(n) ? parseInt(n, 10) : 0;
  }

  function clean(list) {
    var seen = {};
    var out = [];
    (Array.isArray(list) ? list : []).forEach(function (l) {
      if (!l || typeof l !== 'object' || out.length >= MAXLINES) return;
      var v = toInt(l.v);
      var q = toInt(l.q);
      if (v > 0 && q > 0 && !seen[v]) {
        seen[v] = true;
        out.push({ v: v, q: Math.min(MAXQTY, q) });
      }
    });
    return out;
  }

  function read(key) {
    try { return localStorage.getItem(key); } catch (e) { return null; }
  }

  function write(key, value) {
    try { localStorage.setItem(key, value); } catch (e) { /* private mode: the cart lasts this page only */ }
  }

  var mem = null;

  function load() {
    var parsed = [];
    try { parsed = JSON.parse(read(KEY) || '[]'); } catch (e) { /* bad value is ignored */ }
    mem = clean(parsed);
    return mem;
  }

  function lines() { return (mem || load()).map(function (l) { return { v: l.v, q: l.q }; }); }

  function save(list) {
    mem = clean(list);
    write(KEY, JSON.stringify(mem));
  }

  /* ---------- page facts ---------- */

  var userMeta = document.querySelector('meta[name="aisle-user"]');
  var user = userMeta ? (userMeta.getAttribute('content') || '') : '';
  if (!/^\d+$/.test(user)) user = '';

  function apiConfig() {
    var m = document.querySelector('meta[name="shopclass-api"]');
    if (!m) return null;
    try {
      var d = JSON.parse(m.getAttribute('content') || '');
      return d && d.url ? d : null;
    } catch (e) { return null; }
  }

  function endpoint(c, path) { return String(c.url).replace(/\/+$/, '') + '/' + path; }

  var noSync = false;

  function safeUrl(u) {
    try {
      var p = new URL(u, location.href);
      return p.protocol === 'http:' || p.protocol === 'https:' ? p.href : '';
    } catch (e) { return ''; }
  }

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined) n.textContent = text;
    return n;
  }

  function icon(name, small) {
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'ico' + (small ? ' sm' : ''));
    svg.setAttribute('aria-hidden', 'true');
    var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    use.setAttribute('href', '#i-' + name);
    svg.appendChild(use);
    return svg;
  }

  function fill(tpl, v) { return String(tpl).replace('%d', v).replace('%s', v); }

  function toast(text) {
    if (typeof window.aisleToast === 'function') window.aisleToast(text);
  }

  /* ---------- coupon ---------- */

  /* The applied coupon, kept in this browser for the shopper who applied it; the account cart
     holds lines only. A guest's code carries over when they sign in. */
  function coupon() {
    var d = null;
    try { d = JSON.parse(read(CKEY) || 'null'); } catch (e) { /* none */ }
    if (!d || typeof d.code !== 'string' || !/^[A-Z0-9-]{1,40}$/.test(d.code)) return '';
    if (d.user === user) return d.code;
    if (d.user === '' && user !== '') { setCoupon(d.code); return d.code; }
    return '';
  }

  function setCoupon(code) {
    write(CKEY, code ? JSON.stringify({ user: user, code: String(code) }) : '');
  }

  /* ---------- sync with the account ---------- */

  /* BKEY holds the server's cart as this browser last saw it, with its ETag. A pull takes the
     server copy when nothing changed here since; otherwise it lays the local changes on top. A
     write sends If-Match, so a stale browser cannot overwrite what another one saved. */
  var BKEY = 'aisle-cart-base';
  var ready = Promise.resolve();
  var mirrorTimer = 0;
  var pulledAt = 0;

  function fromServer(body) {
    var d = body && (body.data || body);
    return clean(((d && d.lines) || []).map(function (l) { return { v: l && l.variant, q: l && l.qty }; }));
  }

  function toServer(list) {
    return { lines: list.map(function (l) { return { variant: l.v, qty: l.q }; }) };
  }

  function same(a, b) {
    if (a.length !== b.length) return false;
    var m = {};
    a.forEach(function (l) { m[l.v] = l.q; });
    return b.every(function (l) { return m[l.v] === l.q; });
  }

  function readBase() {
    try {
      var d = JSON.parse(read(BKEY) || 'null');
      if (d && d.user === user && typeof d.etag === 'string') return { lines: clean(d.lines), etag: d.etag };
    } catch (e) { /* none */ }
    return null;
  }

  function writeBase(list, etag) {
    write(BKEY, JSON.stringify({ user: user, lines: list, etag: etag || '' }));
  }

  /* The server copy with what changed here since base: added, changed and removed lines. */
  function merge(server, local, base) {
    var out = {};
    var order = [];
    function put(l) { if (!(l.v in out)) order.push(l.v); out[l.v] = l.q; }
    server.forEach(put);
    if (base === null) {
      local.forEach(function (l) { if (!(l.v in out)) put(l); });
    } else {
      var was = {};
      base.forEach(function (l) { was[l.v] = l.q; });
      local.forEach(function (l) { if (was[l.v] !== l.q) put(l); });
      var now = {};
      local.forEach(function (l) { now[l.v] = true; });
      base.forEach(function (l) { if (!now[l.v]) delete out[l.v]; });
    }
    return clean(order.filter(function (v) { return v in out; }).map(function (v) { return { v: v, q: out[v] }; }));
  }

  function cartCall(method, body, etag) {
    var c = apiConfig();
    if (!c || !c.token) return Promise.reject(new Error('no token'));
    var headers = { Accept: 'application/json' };
    headers[c.header] = c.token;
    if (etag) headers['If-Match'] = etag;
    var opts = { method: method, headers: headers, credentials: 'same-origin', cache: 'no-store' };
    if (body !== undefined) {
      headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    return fetch(endpoint(c, 'account/cart'), opts).then(function (r) {
      if (r.status === 401 || r.status === 404) {
        noSync = true; /* signed out, or a core without the saved cart */
        throw new Error('status ' + r.status);
      }
      if (r.status === 412) return { stale: true };
      if (!r.ok) throw new Error('status ' + r.status);
      if (r.status === 204) return { stale: false };
      return r.json().then(function (j) { return { stale: false, lines: fromServer(j), etag: r.headers.get('ETag') || '' }; });
    });
  }

  function applied(list) {
    if (same(list, lines())) return;
    save(list);
    paintBadge();
    announce();
    if (isOpen()) { paintDrawer(); requote(); }
  }

  /* Read the server cart, merge, and write back when the merge differs from it. */
  function pull(tries) {
    return cartCall('GET').then(function (got) {
      var base = readBase();
      var local = lines();
      var merged = base !== null && same(local, base.lines) ? got.lines : merge(got.lines, local, base ? base.lines : null);
      applied(merged);
      write(UKEY, user);
      if (same(merged, got.lines)) {
        writeBase(got.lines, got.etag);
        return null;
      }
      return push(got.etag, tries);
    });
  }

  function push(etag, tries) {
    var sent = lines();
    return cartCall('PUT', toServer(sent), etag).then(function (res) {
      if (res.stale) return (tries || 0) < 2 ? pull((tries || 0) + 1) : null;
      return cartCall('GET').then(function (got) {
        if (same(got.lines, sent)) { writeBase(got.lines, got.etag); return null; }
        /* Another browser wrote since, or a line was dropped: merge again from what was sent. */
        writeBase(sent, '');
        return (tries || 0) < 2 ? pull((tries || 0) + 1) : null;
      });
    });
  }

  function queue(job) {
    ready = ready.then(function () { return noSync ? null : job(); }).catch(function () { /* the next change or page tries again */ });
    return ready;
  }

  function mirror() {
    if (user === '' || noSync) return;
    clearTimeout(mirrorTimer);
    mirrorTimer = setTimeout(function () {
      queue(function () {
        var base = readBase();
        return base && base.etag ? push(base.etag, 0) : pull(0);
      });
    }, 400);
  }

  function sync() {
    var last = read(UKEY) || '';
    if (user === '' && last !== '') {
      /* Signed out: do not leave the last person's cart behind. */
      write(KEY, '[]');
      write(UKEY, '');
      write(BKEY, '');
      mem = [];
      return;
    }
    if (user === '') return;
    pulledAt = Date.now();
    queue(function () { return pull(0); }).then(paintBadge);
  }

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible' && user !== '' && !noSync && Date.now() - pulledAt > 5000) sync();
  });

  /* ---------- badge ---------- */

  function count() {
    return lines().reduce(function (s, l) { return s + l.q; }, 0);
  }

  function paintBadge() {
    var n = count();
    document.querySelectorAll('[data-cart-count]').forEach(function (c) {
      var t = String(n);
      if (c.textContent !== t) c.textContent = t;
      c.hidden = n === 0;
    });
    var h = document.querySelector('[data-cart-heading-count]');
    if (h) h.textContent = n ? fill(T.items, n) : '';
  }

  function bump() {
    document.querySelectorAll('[data-cart-count]').forEach(function (c) {
      c.classList.remove('bump');
      void c.offsetWidth;
      c.classList.add('bump');
    });
  }

  /* ---------- quote ---------- */

  var quoteData = null;
  var quoteFailed = false;
  var quoteTimer = 0;
  var quoteCtl = null;
  var quoteSeq = 0;
  var fresh = 0;
  var waiting = [];
  var dropped = 0;
  var droppedText = '';

  function finishQuote(v) {
    var w = waiting;
    waiting = [];
    w.forEach(function (fn) { fn(v); });
  }

  function requote() {
    clearTimeout(quoteTimer);
    quoteTimer = setTimeout(runQuote, 120);
    return new Promise(function (resolve) { waiting.push(resolve); });
  }

  function runQuote() {
    var list = lines();
    if (quoteCtl) quoteCtl.abort();
    if (!list.length) {
      quoteData = null;
      quoteFailed = false;
      paintDrawer();
      finishQuote(null);
      return;
    }
    var c = apiConfig();
    if (!c) {
      quoteFailed = true;
      paintDrawer();
      finishQuote(null);
      return;
    }
    quoteCtl = new AbortController();
    var mine = ++quoteSeq;
    var url = new URL(endpoint(c, 'cart/quote'), location.href);
    url.searchParams.set('lines', list.map(function (l) { return l.v + ':' + l.q; }).join(','));
    var code = coupon();
    if (code) url.searchParams.set('coupon', code);
    var headers = { Accept: 'application/json' };
    if (c.header && c.token) headers[c.header] = c.token;
    fetch(url.toString(), { headers: headers, signal: quoteCtl.signal, credentials: 'same-origin' }).then(function (r) {
      if (!r.ok) throw new Error('status ' + r.status);
      return r.json();
    }).then(function (body) {
      if (mine !== quoteSeq) return;
      var d = body && (body.data || body);
      if (!d || !Array.isArray(d.lines)) throw new Error('bad quote');
      quoteData = d;
      quoteFailed = false;
      if (code && (d.problems || []).some(function (p) { return p && p.pointer === '/coupon'; })) setCoupon('');
      var gone = prune(d);
      if (gone) {
        dropped = gone;
        droppedText = refusals(d);
      }
      else if (!clamp(d)) dropped = 0;
      paintDrawer();
      finishQuote(d);
    }).catch(function (err) {
      if (mine !== quoteSeq || (err && err.name === 'AbortError')) return;
      quoteData = null;
      quoteFailed = true;
      paintDrawer();
      finishQuote(null);
    });
  }

  /* The site's own words for items it refused, such as a price in another currency. */
  function refusals(d) {
    return (Array.isArray(d.problems) ? d.problems : []).filter(function (p) {
      return p && p.code === 'not_buyable' && typeof p.message === 'string' && p.message !== '';
    }).map(function (p) { return p.message; }).join(' ');
  }

  /* A variant the quote no longer knows (deleted or hidden) leaves the cart. Returns how many left. */
  function prune(d) {
    var known = {};
    d.lines.forEach(function (l) { var v = toInt(l && l.variant); if (v > 0) known[v] = true; });
    var before = lines();
    var after = before.filter(function (l) { return known[l.v]; });
    if (after.length !== before.length) {
      save(after);
      paintBadge();
      mirror();
      announce();
    }
    return before.length - after.length;
  }

  /* A line asking for more than the stock drops to the stock and is quoted again. */
  function clamp(d) {
    var cap = {};
    d.lines.forEach(function (q) {
      var left = q && q.stock_left;
      if (typeof left === 'number' && left > 0) cap[toInt(q.variant)] = left;
    });
    var hit = false;
    var next = lines().map(function (l) {
      if (cap[l.v] && l.q > cap[l.v]) { hit = true; return { v: l.v, q: cap[l.v] }; }
      return l;
    });
    if (hit) {
      save(next);
      paintBadge();
      mirror();
      announce();
      requote();
    }
    return hit;
  }

  /* ---------- price changes ---------- */

  function readPrices() {
    try {
      var d = JSON.parse(read(PKEY) || '{}');
      return d && typeof d === 'object' && !Array.isArray(d) ? d : {};
    } catch (e) { return {}; }
  }

  /* Notices for quoted lines whose unit price differs from the first one seen. A line keeps its old
     price until the shopper says OK, and a line that left the cart is forgotten. */
  function priceNotes(qlines) {
    var seen = readPrices();
    var next = {};
    var notes = [];
    (Array.isArray(qlines) ? qlines : []).forEach(function (q) {
      var v = q && toInt(q.variant);
      if (!(v > 0) || !q.unit) return;
      var now = { n: amountNum(q.unit), f: amountText(q.unit) };
      var was = seen[v];
      if (was && typeof was.n === 'number' && was.n !== now.n) {
        next[v] = was;
        var title = q.listing && q.listing.title ? String(q.listing.title) : '';
        notes.push(String(T.price_changed).replace('%1$s', title).replace('%2$s', String(was.f)).replace('%3$s', now.f));
      } else {
        next[v] = now;
      }
    });
    write(PKEY, JSON.stringify(next));
    return notes;
  }

  function acceptPrices() {
    write(PKEY, '{}');
    if (isOpen()) paintDrawer();
    document.dispatchEvent(new CustomEvent('aisle:prices'));
  }

  function noticeNode(notes) {
    var box = el('div', 'price-note');
    box.setAttribute('role', 'status');
    notes.forEach(function (t) { box.appendChild(el('p', '', t)); });
    var ok = el('button', 'btn btn-ghost', T.ok);
    ok.type = 'button';
    ok.setAttribute('data-price-ok', '');
    box.appendChild(ok);
    return box;
  }

  /* ---------- drawer ---------- */

  var drawer = null;
  var scrim = null;
  var opener = null;

  function isOpen() { return !!drawer && drawer.classList.contains('on'); }

  function amountText(a) {
    if (a && typeof a === 'object') return String(a.formatted || a.amount || '');
    return a === undefined || a === null ? '' : String(a);
  }

  function amountNum(a) {
    var n = parseFloat(a && typeof a === 'object' ? a.amount : a);
    return isFinite(n) ? n : 0;
  }

  function photoUrl(p) {
    if (typeof p === 'string') return safeUrl(p);
    if (p && typeof p === 'object') return safeUrl(String(p.thumbnail || p.preview || p.normal || p.url || ''));
    return '';
  }

  function optionsText(o) {
    if (Array.isArray(o)) return o.map(String).join(' · ');
    if (o && typeof o === 'object') return Object.keys(o).map(function (k) { return String(o[k]); }).join(' · ');
    return '';
  }

  function stepButton(kind, label, name) {
    var b = el('button');
    b.type = 'button';
    b.setAttribute(kind, '');
    b.setAttribute('aria-label', label);
    b.appendChild(icon(name, true));
    return b;
  }

  function lineNode(q, local) {
    var v = toInt(q.variant);
    var node = el('div', 'line' + (v === fresh ? ' fresh' : ''));
    node.setAttribute('data-line', String(v));
    var listing = q.listing && typeof q.listing === 'object' ? q.listing : {};
    var left = q.stock_left;
    var capped = typeof left === 'number' && left > 0;
    var soldOut = q.available === false && !capped;

    var img = el('img');
    img.alt = '';
    img.width = 72;
    img.height = 88;
    img.loading = 'lazy';
    var src = photoUrl(listing.photo);
    if (src) img.src = src;
    node.appendChild(img);

    var mid = el('div');
    var href = safeUrl(String(listing.url || ''));
    var title = el(href ? 'a' : 'b', 'ln-t', String(listing.title || ''));
    if (href) title.href = href;
    mid.appendChild(title);
    var opt = optionsText(q.options);
    if (opt) mid.appendChild(el('div', 'v', opt));

    if (soldOut) {
      mid.appendChild(el('div', 'v sold', T.sold));
    } else {
      var qty = el('div', 'qty');
      qty.setAttribute('role', 'group');
      qty.appendChild(stepButton('data-dec', T.less, 'minus'));
      var out = el('output', 'num', String(local.q));
      qty.appendChild(out);
      var inc = stepButton('data-inc', T.more, 'plus');
      if (capped && local.q >= left) {
        inc.disabled = true;
        inc.title = fill(T.left, left);
      }
      qty.appendChild(inc);
      mid.appendChild(qty);
      if (capped) {
        mid.appendChild(el('div', 'v low', fill(T.left, left)));
      }
    }
    var rm = el('button', 'rm', T.remove);
    rm.type = 'button';
    rm.setAttribute('data-rm', '');
    mid.appendChild(rm);
    node.appendChild(mid);

    var price = el('b', 'num' + (soldOut ? ' struck' : ''), amountText(q.amount));
    node.appendChild(price);
    return node;
  }

  function paintMeter(box, d) {
    box.textContent = '';
    var fo = d && d.free_over;
    if (!fo || typeof fo !== 'object') { box.hidden = true; return; }
    var remaining = amountNum(fo.remaining);
    var sub = d.totals ? amountNum(d.totals.subtotal) : 0;
    var span = el('span');
    var pct = 100;
    if (remaining > 0) {
      var parts = String(T.more_for).split('%s');
      span.appendChild(document.createTextNode(parts[0]));
      span.appendChild(el('b', 'num', amountText(fo.remaining)));
      span.appendChild(document.createTextNode(parts.slice(1).join('%s')));
      pct = sub + remaining > 0 ? Math.max(0, Math.min(100, (sub / (sub + remaining)) * 100)) : 0;
    } else {
      span.appendChild(el('b', '', T.free));
    }
    var track = el('i');
    var bar = el('b');
    bar.style.width = pct.toFixed(1) + '%';
    track.appendChild(bar);
    box.appendChild(span);
    box.appendChild(track);
    box.hidden = false;
  }

  function emptyNode(message) {
    var box = el('div', 'empty');
    box.appendChild(icon('bag'));
    box.appendChild(el('p', '', message));
    var go = el('a', 'btn btn-primary', drawer.getAttribute('data-empty-cta') || 'Start shopping');
    go.href = safeUrl(drawer.getAttribute('data-shop') || '/') || '/';
    box.appendChild(go);
    return box;
  }

  function row(label, value, cls) {
    var r = el('div', 'sumrow' + (cls ? ' ' + cls : ''));
    r.appendChild(el('span', '', label));
    r.appendChild(el('span', 'num', value));
    return r;
  }

  function paintDrawer() {
    if (!drawer) return;
    var box = drawer.querySelector('[data-cart-lines]');
    var foot = drawer.querySelector('[data-cart-foot]');
    var meter = drawer.querySelector('[data-cart-meter]');
    var list = lines();
    box.textContent = '';
    foot.textContent = '';
    paintBadge();

    if (!list.length) {
      meter.hidden = true;
      if (dropped) box.appendChild(el('p', 'empty', droppedText || T.unbuyable));
      box.appendChild(emptyNode(drawer.getAttribute('data-empty') || 'Your cart is empty.'));
      return;
    }
    if (!quoteData) {
      meter.hidden = true;
      if (quoteFailed) {
        var fail = el('div', 'empty');
        fail.appendChild(el('p', '', T.failed));
        var retry = el('button', 'btn btn-ghost', drawer.getAttribute('data-retry') || 'Try again');
        retry.type = 'button';
        retry.setAttribute('data-cart-retry', '');
        fail.appendChild(retry);
        box.appendChild(fail);
      } else {
        box.appendChild(el('p', 'empty', '…'));
      }
      return;
    }
    var byVariant = {};
    quoteData.lines.forEach(function (q) { if (q && typeof q === 'object') byVariant[toInt(q.variant)] = q; });
    var notes = priceNotes(quoteData.lines);
    if (notes.length) box.appendChild(noticeNode(notes));
    if (dropped) {
      var gone = el('div', 'price-note');
      gone.setAttribute('role', 'status');
      gone.appendChild(el('p', '', droppedText || T.unbuyable));
      box.appendChild(gone);
    }
    list.forEach(function (l) {
      if (byVariant[l.v]) box.appendChild(lineNode(byVariant[l.v], l));
    });
    var ships = quoteData.needs_shipping !== false;
    if (ships) paintMeter(meter, quoteData);
    else meter.hidden = true;

    var totals = quoteData.totals || {};
    foot.appendChild(row(drawer.getAttribute('data-subtotal') || 'Subtotal', amountText(totals.subtotal), 'total'));
    if (ships) foot.appendChild(el('p', 'note', drawer.getAttribute('data-note') || ''));
    var go = el('a', 'btn btn-primary btn-block', T.checkout);
    go.href = safeUrl(drawer.getAttribute('data-checkout') || '') || '#';
    foot.appendChild(go);
    var keep = el('button', 'btn btn-ghost btn-block', drawer.getAttribute('data-keep') || 'Keep shopping');
    keep.type = 'button';
    keep.setAttribute('data-cart-close', '');
    foot.appendChild(keep);
    if (fresh) {
      var f = box.querySelector('[data-line="' + fresh + '"]');
      if (f && f.scrollIntoView) f.scrollIntoView({ block: 'nearest' });
      fresh = 0;
    }
  }

  function focusables() {
    return Array.prototype.slice.call(drawer.querySelectorAll('a[href], button:not([disabled])'));
  }

  function open(from) {
    if (!drawer) return;
    if (!isOpen()) opener = from || document.activeElement;
    drawer.removeAttribute('inert');
    drawer.setAttribute('aria-hidden', 'false');
    drawer.classList.add('on');
    if (scrim) scrim.classList.add('on');
    document.documentElement.style.overflow = 'hidden';
    paintDrawer();
    requote();
    var close = drawer.querySelector('[data-cart-close]');
    setTimeout(function () { if (close) close.focus(); }, 50);
  }

  function close() {
    if (!isOpen()) return;
    drawer.classList.remove('on');
    drawer.setAttribute('aria-hidden', 'true');
    drawer.setAttribute('inert', '');
    if (scrim) scrim.classList.remove('on');
    document.documentElement.style.overflow = '';
    if (opener && opener.focus && document.contains(opener)) opener.focus();
    opener = null;
  }

  /* ---------- changes ---------- */

  function announce() {
    document.dispatchEvent(new CustomEvent('aisle:cart', { detail: { lines: lines(), count: count() } }));
  }

  function changed(redraw) {
    paintBadge();
    mirror();
    announce();
    if (redraw && isOpen()) { paintDrawer(); requote(); }
  }

  /* The site says how big a cart may be; the numbers above hold until it answers. */
  function loadLimits() {
    if (limitsAsked) return;
    limitsAsked = true;
    var c = apiConfig();
    if (!c) return;
    var headers = { Accept: 'application/json' };
    if (c.header && c.token) headers[c.header] = c.token;
    fetch(endpoint(c, ''), { headers: headers, credentials: 'same-origin' }).then(function (r) {
      return r.ok ? r.json() : null;
    }).then(function (body) {
      var cart = body && body.data && body.data.cart;
      if (!cart) return;
      if (toInt(cart.max_lines) > 0) MAXLINES = toInt(cart.max_lines);
      if (toInt(cart.max_qty) > 0) MAXQTY = toInt(cart.max_qty);
    }).catch(function () { /* the defaults stay */ });
  }

  function add(variant, qty, opts) {
    loadLimits();
    var v = toInt(variant);
    var q = toInt(qty) || 1;
    if (!(v > 0)) return false;
    var list = lines();
    var hit = list.filter(function (l) { return l.v === v; })[0];
    if (hit) hit.q = Math.min(MAXQTY, hit.q + q);
    else if (list.length >= MAXLINES) { toast(T.failed); return false; }
    else list.unshift({ v: v, q: Math.min(MAXQTY, q) });
    save(list);
    fresh = v;
    bump();
    changed(false);
    if (!opts || opts.open !== false) open();
    else paintBadge();
    return true;
  }

  function set(variant, qty) {
    var v = toInt(variant);
    var q = toInt(qty);
    if (q <= 0) return remove(v);
    save(lines().map(function (l) { return l.v === v ? { v: v, q: Math.min(MAXQTY, q) } : l; }));
    changed(true);
    return true;
  }

  function remove(variant) {
    var v = toInt(variant);
    save(lines().filter(function (l) { return l.v !== v; }));
    changed(true);
    return true;
  }

  function clear() {
    write(PKEY, '{}');
    setCoupon('');
    save([]);
    changed(true);
  }

  window.aisleCart = {
    lines: lines,
    add: add,
    set: set,
    remove: remove,
    clear: clear,
    coupon: coupon,
    setCoupon: setCoupon,
    quote: function () { return requote(); },
    priceNotes: priceNotes,
    clamp: clamp,
    noticeNode: noticeNode,
    photoUrl: photoUrl,
    open: open,
    close: close
  };

  /* ---------- events ---------- */

  function flash(btn) {
    var label = btn.querySelector('.quick-label') || btn;
    if (btn.dataset.was === undefined) btn.dataset.was = label.textContent;
    clearTimeout(+btn.dataset.timer || 0);
    btn.classList.add('done');
    label.textContent = T.added;
    btn.dataset.timer = String(setTimeout(function () {
      btn.classList.remove('done');
      label.textContent = btn.dataset.was;
      delete btn.dataset.was;
    }, 1400));
  }

  document.addEventListener('click', function (e) {
    var t = e.target;
    if (!t || !t.closest) return;
    var openBtn = t.closest('[data-cart-open]');
    if (openBtn) { e.preventDefault(); open(openBtn); return; }
    if (t.closest('[data-cart-close]')) { close(); return; }
    if (t.closest('[data-cart-scrim]')) { close(); return; }
    if (t.closest('[data-cart-retry]')) { quoteFailed = false; paintDrawer(); requote(); return; }

    if (t.closest('[data-price-ok]')) { acceptPrices(); return; }

    var quick = t.closest('[data-quick-add]');
    if (quick) {
      e.preventDefault();
      if (add(quick.getAttribute('data-quick-add'), 1)) flash(quick);
      return;
    }

    if (drawer && drawer.contains(t)) {
      var node = t.closest('[data-line]');
      if (!node) return;
      var v = toInt(node.getAttribute('data-line'));
      var cur = lines().filter(function (l) { return l.v === v; })[0];
      if (!cur) return;
      if (t.closest('[data-rm]')) remove(v);
      else if (t.closest('[data-inc]')) {
        var ql = quoteData && quoteData.lines.filter(function (x) { return x && toInt(x.variant) === v; })[0];
        var cap = ql && ql.stock_left;
        if (typeof cap === 'number' && cap > 0 && cur.q >= cap) return;
        set(v, cur.q + 1);
      }
      else if (t.closest('[data-dec]')) set(v, cur.q - 1);
    }
  });

  document.addEventListener('keydown', function (e) {
    if (!isOpen()) return;
    if (e.key === 'Escape') { e.preventDefault(); close(); return; }
    if (e.key !== 'Tab') return;
    var f = focusables();
    if (!f.length) return;
    var first = f[0];
    var last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });

  window.addEventListener('storage', function (e) {
    if (e.key !== KEY) return;
    load();
    paintBadge();
    if (isOpen()) requote();
  });

  function init() {
    drawer = document.querySelector('[data-cart-drawer]');
    scrim = document.querySelector('[data-cart-scrim]');
    load();
    if (lines().length) loadLimits();
    sync();
    paintBadge();
    ready.then(paintBadge);
    /* A link to #cart (such as a cart reminder mail) opens the drawer once the saved cart is in. */
    if (location.hash === '#cart' && drawer) {
      try { history.replaceState(null, '', location.pathname + location.search); } catch (e) { /* the hash stays */ }
      ready.then(function () { open(); });
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
