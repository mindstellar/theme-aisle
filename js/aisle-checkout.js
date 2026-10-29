/*
 * Aisle checkout. The summary, delivery and payment choices are painted from GET cart/quote; the
 * browser sends variant ids, quantities, the address and method ids, never a price. Placing is
 * POST account/orders with an Idempotency-Key, so a double click or a retry makes one order.
 */
(function () {
  'use strict';

  /* The line about unpaid bank transfer orders. Blocking once the buyer holds as many as allowed. */
  function openOrdersNotice(open, T) {
    var count = open ? Number(open.count) : 0;
    var limit = open ? Number(open.limit) : 0;
    if (!(count >= 1) || !(limit > 0)) return { text: '', blocking: false };
    var tpl = count === 1 ? T.open_one : T.open_many;
    return { text: String(tpl).replace('%1$d', count).replace('%2$d', limit), blocking: count >= limit };
  }
  window.aisleOpenOrdersNotice = openOrdersNotice;

  /* The limit is on unpaid bank transfer orders only, so other gateways get no line. */
  function openOrdersFor(quote, gateway, T) {
    return quote && gateway === 'offline' ? openOrdersNotice(quote.open_orders, T) : { text: '', blocking: false };
  }
  window.aisleOpenOrdersFor = openOrdersFor;

  /* An order cannot go through without a clean quote, or while the open-orders limit blocks it. */
  function placeBlocked(s) {
    return !s.quote || !!s.problems || !!s.openBlock;
  }
  window.aislePlaceBlocked = placeBlocked;

  /* The Place order button also waits for a gateway and for a request in flight. */
  function canPlace(s) {
    return !s.busy && !!s.gateway && !placeBlocked(s);
  }
  window.aisleCanPlace = canPlace;

  var form = document.querySelector('[data-checkout]');
  if (!form) return;

  var T = {
    required: 'This field is required.', pin: 'PIN code needs 6 digits.', phone: 'Enter a mobile number with at least 8 digits.',
    pin_label: 'PIN code', zip_label: 'Postcode', free: 'Free', empty: 'Your cart is empty.', back: 'Back to the shop', qty: 'Qty %d',
    left: 'Only %d left', sold: 'Sold out', fix_cart: 'Some items need your attention. Change them in your cart.',
    no_pay: 'No payment method is available right now.', fee: '+%s', placing: 'Placing your order…', place: 'Place order',
    failed: 'We could not place your order. Please try again.', quote_fail: 'We could not price your cart. Please try again.',
    placed: 'Order %s placed', placed_sub: 'Thank you. We have your order.', view_order: 'View your order',
    unavail: 'That payment method is not available. Pick another.', see_orders: 'See your orders', redirect: 'Taking you to payment…',
    coupon_bad: 'Enter a valid coupon code.', offline: 'You are offline. Check your connection and try again.',
    slow: 'Too many tries. Wait a minute and try again.',
    open_one: 'You have %1$d unpaid bank transfer order. You can hold %2$d at once.',
    open_many: 'You have %1$d unpaid bank transfer orders. You can hold %2$d at once.',
    open_blocked: 'Pay for or cancel an unpaid bank transfer order first.', retry: 'Try again', includes: 'Includes %1$s %2$s', tax_name: 'Tax'
  };
  try {
    var raw = document.getElementById('aisle-checkout-i18n');
    if (raw) Object.assign(T, JSON.parse(raw.textContent || '{}'));
  } catch (e) { /* keep defaults */ }

  var FIELDS = ['name', 'phone', 'line1', 'postcode', 'city', 'state', 'country'];
  var elShip = form.querySelector('[data-shipping]');
  var elPay = form.querySelector('[data-gateways]');
  var elLines = form.querySelector('[data-lines]');
  var elTotals = form.querySelector('[data-totals]');
  var elAlert = form.querySelector('[data-alert]');
  var elGwErr = form.querySelector('[data-gateway-err]');
  var elTopErr = form.querySelector('[data-top-err]');
  var elOpen = form.querySelector('[data-open-orders]');
  var elTop = form.querySelector('[data-co-top]');
  var elTopTotal = form.querySelector('[data-co-top-total]');
  var btn = form.querySelector('[data-place]');
  var btnText = btn.querySelector('span');
  var placed = document.querySelector('[data-placed]');
  var elCoupon = form.querySelector('[data-coupon]');
  var elCouponIn = form.querySelector('[data-coupon-input]');
  var elCouponErr = form.querySelector('[data-coupon-err]');
  var elCouponOk = form.querySelector('[data-coupon-ok]');
  var elTaxNote = form.querySelector('[data-tax-note]');

  var quote = null;
  var shipping = '';
  var gateway = '';
  var busy = false;
  var openBlock = false;
  var seq = 0;
  var ctl = null;
  var coupon = '';
  var couponMsg = '';
  var fieldMsg = { postcode: false, country: false };
  var timer = 0;
  /* The saved address chosen in core's address picker; '' for a typed one. */
  var addressId = pickedAddress();

  /* ---------- helpers ---------- */

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined) n.textContent = text;
    return n;
  }

  function fill(tpl, v) { return String(tpl).replace('%d', v).replace('%s', v); }

  function toInt(n) {
    if (typeof n === 'number') return Number.isInteger(n) ? n : 0;
    return typeof n === 'string' && /^\d{1,10}$/.test(n) ? parseInt(n, 10) : 0;
  }

  function amountText(a) {
    if (a && typeof a === 'object') return String(a.formatted || a.amount || '');
    return a === undefined || a === null ? '' : String(a);
  }

  /* A discount line comes signed ("-₹320" or "₹-320"); the page adds its own minus. */
  function unsigned(a) { return amountText(a).replace(/^([^\d\-−]*)[-−]\s*/, '$1'); }

  function amountNum(a) {
    var n = parseFloat(a && typeof a === 'object' ? a.amount : a);
    return isFinite(n) ? n : 0;
  }

  function safeUrl(u) {
    if (typeof u !== 'string' || u === '') return '';
    try {
      var p = new URL(u, location.href);
      return p.protocol === 'http:' || p.protocol === 'https:' ? p.href : '';
    } catch (e) { return ''; }
  }

  function apiConfig() {
    var m = document.querySelector('meta[name="shopclass-api"]');
    if (!m) return null;
    try {
      var d = JSON.parse(m.getAttribute('content') || '');
      return d && d.url && d.token ? d : null;
    } catch (e) { return null; }
  }

  function endpoint(c, path) { return String(c.url).replace(/\/+$/, '') + '/' + path; }

  function pickedAddress() {
    var r = form.querySelector('[data-address-picker] input[type=radio]:checked');
    return r ? String(r.value) : '';
  }

  function saveAddress() {
    var box = form.elements.save_address;
    return !!(box && box.checked && !box.closest('[hidden]'));
  }

  function cartLines() {
    return window.aisleCart ? window.aisleCart.lines() : [];
  }

  /* The postcode is sent only when it passes the same rule as the form. */
  function pinOk(v) {
    return value('country') === 'IN' ? /^\d{6}$/.test(v) : /^[A-Za-z0-9 -]{3,10}$/.test(v);
  }

  function later() {
    clearTimeout(timer);
    timer = setTimeout(runQuote, 500);
  }

  function value(name) {
    var f = form.elements[name];
    return f ? String(f.value).trim() : '';
  }

  /* ---------- quote ---------- */

  function runQuote() {
    var list = cartLines();
    if (ctl) ctl.abort();
    if (!list.length) {
      quote = null;
      paint();
      return;
    }
    var c = apiConfig();
    if (!c) {
      showAlert(T.quote_fail);
      return;
    }
    ctl = new AbortController();
    var mine = ++seq;
    var url = new URL(endpoint(c, 'cart/quote'), location.href);
    url.searchParams.set('lines', list.map(function (l) { return l.v + ':' + l.q; }).join(','));
    if (shipping) url.searchParams.set('shipping', shipping);
    if (gateway) url.searchParams.set('gateway', gateway);
    if (ships()) {
      var cc = value('country');
      var st = value('state');
      var ci = value('city');
      var pc = value('postcode');
      if (/^[A-Za-z]{2}$/.test(cc)) url.searchParams.set('country', cc.toUpperCase());
      if (st) url.searchParams.set('state', st.slice(0, 100));
      if (ci) url.searchParams.set('city', ci.slice(0, 100));
      if (pc && pinOk(pc)) url.searchParams.set('postcode', pc);
    }
    if (coupon) url.searchParams.set('coupon', coupon);
    var headers = { Accept: 'application/json' };
    headers[c.header] = c.token;
    fetch(url.toString(), { headers: headers, signal: ctl.signal, credentials: 'same-origin' }).then(function (r) {
      if (!r.ok) { var bad = new Error('status ' + r.status); bad.status = r.status; throw bad; }
      return r.json();
    }).then(function (body) {
      if (mine !== seq) return;
      var d = body && (body.data || body);
      if (!d || !Array.isArray(d.lines)) throw new Error('bad quote');
      quote = d;
      showAlert('');
      takeFieldProblems(d);
      paint();
    }).catch(function (err) {
      if (mine !== seq || (err && err.name === 'AbortError')) return;
      quote = null;
      showAlert(failText(err && err.status, T.quote_fail), true);
      paint();
    });
  }

  /* ---------- painting ---------- */

  /* A cart of downloads only has no address or delivery step. */
  function ships() { return !quote || quote.needs_shipping !== false; }

  function paintSteps() {
    var n = 0;
    form.querySelectorAll('[data-step]').forEach(function (sec) {
      var step = sec.getAttribute('data-step');
      sec.hidden = !ships() && (step === 'address' || step === 'delivery');
      var num = sec.querySelector('h2 i');
      if (!sec.hidden && num) num.textContent = String(++n);
    });
  }

  function showAlert(text, retry) {
    elAlert.textContent = text || '';
    elAlert.hidden = !text;
    if (text && retry) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'linkbtn';
      b.setAttribute('data-quote-retry', '');
      b.textContent = T.retry;
      elAlert.appendChild(document.createTextNode(' '));
      elAlert.appendChild(b);
    }
  }

  /* The words for a failed request: offline and rate limits get their own line. */
  function failText(status, fallback) {
    if (window.navigator && navigator.onLine === false) return T.offline;
    return status === 429 ? T.slow : fallback;
  }

  function problemList() { return quote && Array.isArray(quote.problems) ? quote.problems : []; }

  /* A problem with a pointer belongs to a field; one without belongs to a cart line. */
  function pointerOf(p) { return p && typeof p.pointer === 'string' ? p.pointer : ''; }

  function takeFieldProblems(d) {
    var list = Array.isArray(d.problems) ? d.problems : [];
    var bad = list.filter(function (p) { return pointerOf(p) === '/coupon'; })[0];
    if (bad) {
      /* A refused code is dropped, so the shopper can order without it. */
      couponMsg = String(bad.message || T.coupon_bad);
      coupon = '';
    }
    ['postcode', 'country'].forEach(function (name) {
      var hit = list.filter(function (p) { return pointerOf(p) === '/address/' + name; })[0];
      if (hit && addressId) {
        /* The fields are hidden behind a saved address, so the alert carries it. */
        showAlert(String(hit.message || T.required));
      } else if (hit) {
        setError(name, String(hit.message || T.required));
        fieldMsg[name] = true;
      } else if (fieldMsg[name]) {
        setError(name, '');
        fieldMsg[name] = false;
      }
    });
  }

  function hasProblems() {
    if (!quote) return true;
    var blocking = problemList().some(function (p) { var at = pointerOf(p); return at !== '/coupon'; });
    if (blocking) return true;
    return quote.lines.some(function (l) { return l && l.available === false; });
  }

  function lineProblems() {
    return problemList().some(function (p) { return !pointerOf(p); }) || quote.lines.some(function (l) { return l && l.available === false; });
  }

  function paintLines() {
    elLines.textContent = '';
    if (!quote) {
      if (!cartLines().length) {
        elLines.appendChild(el('p', 'co-note', T.empty));
        var shop = safeUrl(form.getAttribute('data-shop'));
        if (shop) {
          var back = el('a', 'btn btn-ghost', T.back);
          back.href = shop;
          elLines.appendChild(back);
        }
      }
      return;
    }
    quote.lines.forEach(function (l) {
      var row = el('div', 'sline');
      var im = el('div', 'im');
      var listing = l.listing || {};
      var photo = window.aisleCart && window.aisleCart.photoUrl ? window.aisleCart.photoUrl(listing.photo) : '';
      if (photo) {
        var img = el('img');
        img.src = photo;
        img.alt = '';
        img.width = 56;
        img.height = 64;
        img.loading = 'lazy';
        im.appendChild(img);
      }
      im.appendChild(el('em', '', String(toInt(l.qty))));
      var mid = el('div');
      mid.appendChild(el('b', '', String(listing.title || '')));
      var opts = l.options && typeof l.options === 'object' ? Object.keys(l.options).map(function (k) { return l.options[k]; }).join(' · ') : '';
      if (opts) mid.appendChild(el('small', 'v', opts));
      var bad = l.available === false;
      if (bad) {
        var left = toInt(l.stock_left);
        mid.appendChild(el('small', 'v low', left > 0 ? fill(T.left, left) : T.sold));
      }
      row.appendChild(im);
      row.appendChild(mid);
      row.appendChild(el('span', 'num' + (bad ? ' struck' : ''), amountText(l.amount)));
      elLines.appendChild(row);
    });
  }

  function radio(name, id, checked, title, sub, right) {
    var lab = el('label', 'radio');
    var inp = el('input');
    inp.type = 'radio';
    inp.name = name;
    inp.value = id;
    inp.checked = checked;
    lab.appendChild(inp);
    var span = el('span', '', title);
    if (sub) span.appendChild(el('small', '', sub));
    lab.appendChild(span);
    lab.appendChild(el('b', 'num', right || ''));
    return lab;
  }

  function paintChoices() {
    var focus = document.activeElement && document.activeElement.type === 'radio' ? document.activeElement.name + '=' + document.activeElement.value : '';
    elShip.textContent = '';
    elPay.textContent = '';
    if (!quote) return;
    var methods = Array.isArray(quote.shipping_methods) ? quote.shipping_methods : [];
    if (!methods.some(function (m) { return String(m.id) === shipping; })) {
      shipping = methods.length ? String(methods[0].id) : '';
    }
    methods.forEach(function (m) {
      var cost = amountNum(m.amount) > 0 ? amountText(m.amount) : T.free;
      elShip.appendChild(radio('ship', String(m.id), String(m.id) === shipping, String(m.label || ''), String(m.eta || ''), cost));
    });
    var gws = Array.isArray(quote.gateways) ? quote.gateways : [];
    if (!gws.some(function (g) { return String(g.id) === gateway; })) {
      gateway = gws.length ? String(gws[0].id) : '';
    }
    gws.forEach(function (g) {
      var fee = amountNum(g.fee) > 0 ? fill(T.fee, amountText(g.fee)) : '';
      elPay.appendChild(radio('pay', String(g.id), String(g.id) === gateway, String(g.name || ''), '', fee));
    });
    if (!gws.length) elPay.appendChild(el('p', 'co-note', T.no_pay));
    if (focus) {
      var again = form.querySelector('input[type=radio][name="' + focus.split('=')[0] + '"][value="' + focus.split('=')[1] + '"]');
      if (again) again.focus();
    }
  }

  function row(label, a, cls) {
    var r = el('div', 'sumrow' + (cls ? ' ' + cls : ''));
    r.appendChild(el('span', '', label));
    r.appendChild(el('span', 'num', a));
    return r;
  }

  function paintTotals() {
    elTotals.textContent = '';
    if (elTop) elTop.hidden = true;
    if (!quote || !quote.totals) return;
    var t = quote.totals;
    if (elTop && elTopTotal) { elTopTotal.textContent = amountText(t.total); elTop.hidden = false; }
    elTotals.appendChild(row(labels.subtotal, amountText(t.subtotal)));
    if (ships()) elTotals.appendChild(row(labels.delivery, amountNum(t.shipping) > 0 ? amountText(t.shipping) : T.free));
    var mine = couponLine();
    (quote.discounts || []).forEach(function (d) {
      if (d !== mine) elTotals.appendChild(row(String(d.label || labels.discount), '−' + unsigned(d.amount)));
    });
    (quote.taxes || []).forEach(function (x) {
      if (x && x.included !== true) elTotals.appendChild(row(String(x.label || labels.tax), amountText(x.amount)));
    });
    (quote.fees || []).forEach(function (x) { elTotals.appendChild(row(String(x.label || labels.fee), amountText(x.amount))); });
    elTotals.appendChild(row(labels.total, amountText(t.total), 'total'));
  }

  /* The discount line the applied code produced: its label starts with the code. */
  function couponLine() {
    if (!coupon || !quote) return null;
    var list = Array.isArray(quote.discounts) ? quote.discounts : [];
    for (var i = 0; i < list.length; i++) {
      if (list[i] && String(list[i].label || '').indexOf(coupon) === 0) return list[i];
    }
    return null;
  }

  function paintCoupon() {
    var line = couponLine();
    elCoupon.hidden = !!line;
    elCouponOk.hidden = !line;
    elCouponOk.querySelector('[data-coupon-label]').textContent = line ? String(line.label) : '';
    elCouponOk.querySelector('[data-coupon-amt]').textContent = line ? '−' + unsigned(line.amount) : '';
    elCouponErr.textContent = couponMsg;
    elCouponErr.hidden = !couponMsg;
    if (couponMsg) elCouponIn.setAttribute('aria-invalid', 'true'); else elCouponIn.removeAttribute('aria-invalid');
  }

  /* "Includes GST ₹76.12" from the taxes already inside the prices. */
  function paintTaxNote() {
    var names = [];
    var sum = 0;
    ((quote && quote.taxes) || []).forEach(function (x) {
      if (!x || x.included !== true) return;
      var n = String(x.label || '').replace(/\s*[\d.,]+\s*%\s*$/, '') || T.tax_name;
      if (names.indexOf(n) < 0) names.push(n);
      sum += amountNum(x.amount);
    });
    if (!names.length) { elTaxNote.hidden = true; elTaxNote.textContent = ''; return; }
    var inc = quote.totals && quote.totals.tax_included;
    var amt = amountNum(inc) > 0 ? amountText(inc) : String(sum);
    elTaxNote.textContent = String(T.includes).replace('%1$s', names.join(' + ')).replace('%2$s', amt);
    elTaxNote.hidden = false;
  }

  var labels = { subtotal: 'Subtotal', delivery: 'Delivery', discount: 'Discount', tax: 'Tax', fee: 'Fee', total: 'Total' };
  try {
    var lj = document.getElementById('aisle-checkout-i18n');
    var lo = lj ? JSON.parse(lj.textContent || '{}') : {};
    ['subtotal', 'delivery', 'discount', 'tax', 'fee', 'total'].forEach(function (k) { if (lo['l_' + k]) labels[k] = lo['l_' + k]; });
  } catch (e) { /* keep defaults */ }

  function paintPriceNotes() {
    var box = form.querySelector('[data-price-notes]');
    if (!box) return;
    box.textContent = '';
    var notes = quote && window.aisleCart && window.aisleCart.priceNotes ? window.aisleCart.priceNotes(quote.lines) : [];
    box.hidden = !notes.length;
    if (notes.length) box.appendChild(window.aisleCart.noticeNode(notes));
  }

  function paintOpenOrders() {
    var n = openOrdersFor(quote, gateway, T);
    openBlock = n.blocking;
    btn.title = n.blocking ? T.open_blocked : '';
    if (!elOpen) return;
    elOpen.textContent = '';
    elOpen.hidden = !n.text;
    elOpen.classList.toggle('is-blocking', n.blocking);
    if (n.blocking) elOpen.setAttribute('role', 'alert'); else elOpen.removeAttribute('role');
    if (!n.text) return;
    elOpen.appendChild(document.createTextNode(n.text + ' '));
    var url = safeUrl(form.getAttribute('data-orders-url'));
    if (url) {
      var a = document.createElement('a');
      a.href = url;
      a.textContent = T.see_orders;
      elOpen.appendChild(a);
    }
  }

  function paint() {
    paintSteps();
    paintPriceNotes();
    paintLines();
    paintChoices();
    paintOpenOrders();
    paintTotals();
    paintCoupon();
    paintTaxNote();
    if (quote && lineProblems()) showAlert(T.fix_cart);
    else if (quote) {
      var ship = problemList().filter(function (p) { return pointerOf(p) === '/shipping_method'; })[0];
      if (ship) showAlert(String(ship.message || T.failed));
    }
    btn.disabled = !canPlace(placeState(busy));
  }

  /* ---------- fields ---------- */

  function fieldBox(name) { return form.querySelector('[data-field="' + name + '"]'); }

  function setError(name, text) {
    var box = fieldBox(name);
    if (!box) return false;
    var err = box.querySelector('.err');
    var input = box.querySelector('input, select');
    box.classList.toggle('bad', !!text);
    if (err) {
      err.textContent = text || '';
      err.hidden = !text;
      if (!err.id) err.id = 'co-err-' + name;
    }
    if (input) {
      if (text) { input.setAttribute('aria-invalid', 'true'); if (err) input.setAttribute('aria-describedby', err.id); }
      else { input.removeAttribute('aria-invalid'); input.removeAttribute('aria-describedby'); }
    }
    return true;
  }

  function validate() {
    if (addressId) return true;
    var first = '';
    FIELDS.forEach(function (n) {
      var v = value(n);
      var msg = '';
      if (!ships()) msg = '';
      else if (!v) msg = T.required;
      else if (n === 'postcode' && value('country') === 'IN' && !/^\d{6}$/.test(v)) msg = T.pin;
      else if (n === 'phone' && v.replace(/\D/g, '').length < 8) msg = T.phone;
      setError(n, msg);
      if (msg && !first) first = n;
    });
    if (first) {
      var f = form.elements[first];
      if (f) f.focus();
    }
    return !first;
  }

  function paintPostcodeLabel() {
    var lab = form.querySelector('[data-postcode-label]');
    if (lab) lab.textContent = value('country') === 'IN' ? T.pin_label : T.zip_label;
  }

  /* ---------- place ---------- */

  function body() {
    return {
      lines: cartLines().map(function (l) { return { variant: l.v, qty: l.q }; }),
      address: !ships() ? {} : addressId ? { id: addressId } : {
        name: value('name'), phone: value('phone'), line1: value('line1'), city: value('city'),
        state: value('state'), postcode: value('postcode'), country: value('country'), save: saveAddress()
      },
      shipping_method: ships() ? shipping : '',
      gateway: gateway,
      coupon: coupon || undefined
    };
  }

  /* The same order data reuses its key, so a retry after a dropped answer cannot place twice. */
  function keyFor(payload) {
    var sig = JSON.stringify(payload);
    var key = '';
    try {
      var saved = JSON.parse(sessionStorage.getItem('aisle-co-key') || 'null');
      if (saved && saved.sig === sig && typeof saved.key === 'string') key = saved.key;
    } catch (e) { /* new key */ }
    if (!key) {
      key = window.crypto && crypto.randomUUID ? crypto.randomUUID() : 'k' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12);
      try { sessionStorage.setItem('aisle-co-key', JSON.stringify({ sig: sig, key: key })); } catch (e) { /* works without it */ }
    }
    return key;
  }

  function placeState(on) {
    return { busy: on, quote: quote, problems: !quote || hasProblems(), gateway: gateway, openBlock: openBlock };
  }

  function setBusy(on) {
    busy = on;
    btn.disabled = !canPlace(placeState(on));
    btn.setAttribute('aria-busy', on ? 'true' : 'false');
    btnText.textContent = on ? T.placing : T.place;
  }

  function place() {
    var c = apiConfig();
    if (!c) { showAlert(T.failed); return; }
    var payload = body();
    var headers = { Accept: 'application/json', 'Content-Type': 'application/json', 'Idempotency-Key': keyFor(payload) };
    headers[c.header] = c.token;
    setBusy(true);
    showAlert('');
    fetch(endpoint(c, 'account/orders'), { method: 'POST', headers: headers, credentials: 'same-origin', body: JSON.stringify(payload) })
      .then(function (r) { return r.json().catch(function () { return null; }).then(function (j) { return { status: r.status, json: j }; }); })
      .then(function (res) {
        if (res.status === 201 || res.status === 200) { success(res.json); return; }
        failure(res);
      })
      .catch(function () { setBusy(false); showAlert(failText(0, T.failed)); });
  }

  function failure(res) {
    setBusy(false);
    var j = res.json || {};
    if (res.status !== 422) {
      showAlert(failText(res.status, T.failed));
      return;
    }
    var errs = Array.isArray(j.errors) ? j.errors : [];
    var shown = false;
    var refresh = false;
    elGwErr.hidden = true;
    if (elTopErr) elTopErr.hidden = true;
    errs.forEach(function (e) {
      var p = String(e && e.pointer || '');
      var m = /^\/address\/(\w+)/.exec(p);
      if (m && (addressId || m[1] === 'id')) {
        showAlert(String(e.message || T.failed));
        shown = true;
        refresh = true;
      } else if (m) {
        var name = m[1];
        if (setError(name, String(e.message || T.required))) {
          if (!shown && form.elements[name]) form.elements[name].focus();
          shown = true;
        }
      } else if (p === '/coupon') {
        couponMsg = String(e.message || T.coupon_bad);
        coupon = '';
        shown = true;
        refresh = true;
      } else if (p === '/shipping_method') {
        showAlert(String(e.message || T.failed));
        shown = true;
        refresh = true;
      } else if (p === '/gateway') {
        var many = e.code === 'too_many_open' && e.message;
        var target = many && elTopErr ? elTopErr : elGwErr;
        target.textContent = many ? String(e.message) + ' ' : T.unavail;
        var ordersUrl = many ? safeUrl(form.getAttribute('data-orders-url')) : '';
        if (ordersUrl) {
          var a = document.createElement('a');
          a.href = ordersUrl;
          a.textContent = T.see_orders;
          target.appendChild(a);
        }
        target.hidden = false;
        if (many && target === elTopErr) {
          target.scrollIntoView({ block: 'center' });
          target.focus({ preventScroll: true });
        }
        shown = true;
        refresh = true;
      } else if (/^\/lines\//.test(p)) {
        refresh = true;
      }
    });
    if (refresh) {
      try { sessionStorage.removeItem('aisle-co-key'); } catch (e) { /* ignore */ }
      runQuote();
    }
    if (!shown && !refresh) showAlert(String(j.detail || T.failed));
  }

  function runScripts(box) {
    box.querySelectorAll('script').forEach(function (old) {
      var s = document.createElement('script');
      Array.prototype.forEach.call(old.attributes, function (a) { s.setAttribute(a.name, a.value); });
      s.textContent = old.textContent;
      old.parentNode.replaceChild(s, old);
    });
  }

  function success(json) {
    var d = (json && json.data) || json || {};
    var next = d.next || (json && json.next) || null;
    var id = toInt(d.id);
    try { sessionStorage.removeItem('aisle-co-key'); } catch (e) { /* ignore */ }
    if (window.aisleCart) window.aisleCart.clear();

    if (next && next.kind === 'redirect') {
      var url = safeUrl(next.payload);
      if (url) {
        btnText.textContent = T.redirect;
        location.assign(url);
        return;
      }
    }

    form.hidden = true;
    placed.hidden = false;
    placed.querySelector('[data-placed-title]').textContent = fill(T.placed, String(d.number || (id ? '#' + id : '')));
    placed.querySelector('[data-placed-sub]').textContent = T.placed_sub;
    var link = placed.querySelector('[data-placed-link]');
    var tpl = form.getAttribute('data-order-url') || '';
    if (id && tpl) link.href = tpl.replace('{id}', String(id));
    var box = placed.querySelector('[data-intent]');
    box.textContent = '';
    if (next && next.kind === 'html' && typeof next.payload === 'string') {
      box.innerHTML = next.payload; /* the gateway's own markup, printed as given */
      runScripts(box);
    }
    placed.focus();
    window.scrollTo(0, 0);
  }

  /* ---------- events ---------- */

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (busy) return;
    if (!validate()) return;
    if (placeBlocked(placeState(false))) { paint(); return; }
    place();
  });

  form.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('[data-quote-retry]')) { e.preventDefault(); showAlert(''); runQuote(); }
  });

  form.addEventListener('change', function (e) {
    var t = e.target;
    if (!t || !t.name) return;
    if (t.name === 'ship') { shipping = t.value; runQuote(); }
    else if (t.name === 'pay') { gateway = t.value; elGwErr.hidden = true; runQuote(); }
    else if (t.name === 'country') { paintPostcodeLabel(); runQuote(); }
    else if (t.name === 'postcode' || t.name === 'state' || t.name === 'city') { later(); }
  });

  function applyCoupon() {
    var code = elCouponIn.value.replace(/\s+/g, '').toUpperCase();
    if (!/^[A-Z0-9-]{1,40}$/.test(code)) {
      couponMsg = T.coupon_bad;
      paintCoupon();
      return;
    }
    couponMsg = '';
    coupon = code;
    runQuote();
  }

  form.querySelector('[data-coupon-apply]').addEventListener('click', applyCoupon);
  elCouponIn.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); applyCoupon(); }
  });
  elCouponIn.addEventListener('input', function () {
    if (couponMsg) { couponMsg = ''; paintCoupon(); }
  });
  form.querySelector('[data-coupon-remove]').addEventListener('click', function () {
    coupon = '';
    couponMsg = '';
    elCouponIn.value = '';
    runQuote();
    elCouponIn.focus();
  });

  form.addEventListener('input', function (e) {
    var t = e.target;
    if (t && t.name && FIELDS.indexOf(t.name) > -1) { setError(t.name, ''); fieldMsg[t.name] = false; }
    if (t && (t.name === 'postcode' || t.name === 'state' || t.name === 'city')) later();
  });

  /* Core's picker filled the fields from a saved address, or cleared them for a new one. */
  form.addEventListener('osc:address', function (e) {
    addressId = e.detail && e.detail.id ? String(e.detail.id) : '';
    FIELDS.forEach(function (n) { setError(n, ''); fieldMsg[n] = false; });
    showAlert('');
    paintPostcodeLabel();
    runQuote();
  });

  document.addEventListener('aisle:prices', paintPriceNotes);
  document.addEventListener('aisle:cart', function () { if (!busy) runQuote(); });

  paintPostcodeLabel();
  paint();
  if (window.aisleCart) {
    /* Wait for the signed-in cart to merge, then price what is there. */
    var go = window.aisleCart.quote ? window.aisleCart.quote() : Promise.resolve();
    Promise.resolve(go).then(runQuote);
  } else {
    runQuote();
  }
})();
