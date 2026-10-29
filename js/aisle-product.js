/*
 * Aisle product page: gallery (thumbs, dots, swipe, zoom), variant pickers, delivery check, alerts, share, recently viewed.
 */
(function () {
  'use strict';

  var KEY = 'aisle-recent';

  function gallery() {
    var root = document.querySelector('[data-gallery]');
    if (!root) return;
    var main = root.querySelector('[data-main]');
    var img = main && main.querySelector('img');
    var views = root.querySelectorAll('.thumbs [data-view]');
    var dots = root.querySelectorAll('.dots [data-view]');
    var count = Math.max(views.length, dots.length);
    var cur = 0;
    var sources = [];
    views.forEach(function (b) { sources[+b.dataset.view] = b.dataset.src; });

    function show(i) {
      if (!img || !count) return;
      cur = (i + count) % count;
      if (sources[cur]) img.src = sources[cur];
      root.querySelectorAll('[data-view]').forEach(function (b) {
        b.setAttribute('aria-current', String(+b.dataset.view === cur));
      });
      zoom(false);
    }

    /* Zoom is for mouse and keyboard; on touch a tap does nothing and a swipe changes photo. */
    function zoom(on, e) {
      if (!main) return;
      main.classList.toggle('zoom', on);
      if (main.hasAttribute('aria-pressed')) main.setAttribute('aria-pressed', String(on));
      if (img) img.style.transformOrigin = on && e ? origin(e) : '';
    }

    function origin(e) {
      var r = main.getBoundingClientRect();
      return ((e.clientX - r.left) / r.width * 100) + '% ' + ((e.clientY - r.top) / r.height * 100) + '%';
    }

    root.addEventListener('click', function (e) {
      var b = e.target.closest('[data-view]');
      if (b) show(+b.dataset.view);
    });

    if (!main || !img) return;
    var pointer = '';
    main.addEventListener('pointerdown', function (e) { pointer = e.pointerType; });
    main.addEventListener('click', function (e) {
      var by = pointer;
      pointer = '';
      if (by === 'touch' || by === 'pen') return;
      zoom(!main.classList.contains('zoom'), by === 'mouse' ? e : null);
    });
    main.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && main.classList.contains('zoom')) zoom(false);
    });
    main.addEventListener('mousemove', function (e) {
      if (main.classList.contains('zoom')) img.style.transformOrigin = origin(e);
    });

    var x0 = null;
    main.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
    main.addEventListener('touchend', function (e) {
      if (x0 === null || count < 2) return;
      var dx = e.changedTouches[0].clientX - x0;
      x0 = null;
      if (Math.abs(dx) > 40) show(cur + (dx < 0 ? 1 : -1));
    }, { passive: true });
  }

  function toast(text) {
    if (typeof window.aisleToast === 'function') window.aisleToast(text);
  }

  function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(text);
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    var ok = false;
    try { ok = document.execCommand('copy'); } catch (e) {}
    ta.remove();
    return ok ? Promise.resolve() : Promise.reject(new Error('copy'));
  }

  /* The phone share sheet when the browser has one; else a menu (plain http has neither share nor clipboard API). */
  function share() {
    var btn = document.querySelector('[data-share]');
    if (!btn) return;
    var menu = document.querySelector('[data-share-menu]');
    var url = btn.dataset.url || location.href;

    function open(on) {
      if (!menu) return;
      menu.hidden = !on;
      btn.setAttribute('aria-expanded', String(on));
    }

    btn.addEventListener('click', function () {
      if (navigator.share) {
        navigator.share({ title: btn.dataset.title, url: url }).catch(function () {});
        return;
      }
      if (!menu) {
        copyText(url).then(function () { toast(btn.dataset.copied || 'Link copied'); }, function () {});
        return;
      }
      open(menu.hidden);
      if (!menu.hidden) menu.querySelector('button, a').focus();
    });
    if (!menu) return;
    menu.addEventListener('click', function (e) {
      var item = e.target.closest('button, a');
      if (!item) return;
      if (item.hasAttribute('data-share-copy')) {
        copyText(url).then(function () { toast(btn.dataset.copied || 'Link copied'); }, function () { window.prompt('', url); });
      }
      open(false);
    });
    document.addEventListener('click', function (e) {
      if (!menu.hidden && !menu.contains(e.target) && !btn.contains(e.target)) open(false);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !menu.hidden) { open(false); btn.focus(); }
    });
  }

  function pickers() {
    var info = document.querySelector('.info[data-variants]');
    if (!info) return;
    var rows = [];
    var text = {};
    try {
      rows = JSON.parse(info.dataset.variants);
      text = JSON.parse(info.dataset.i18n || '{}');
    } catch (e) { return; }
    if (!Array.isArray(rows) || !rows.length) return;

    var groups = Array.prototype.map.call(info.querySelectorAll('.opt[data-option]'), function (el) {
      return { name: el.dataset.option, el: el, label: el.querySelector('[data-option-value]'), buttons: el.querySelectorAll('button[data-value]') };
    });
    var names = groups.map(function (g) { return g.name; });
    var stock = info.querySelector('[data-stock]');
    var priceEl = info.querySelector('[data-price]');
    var wasEl = info.querySelector('[data-was]');
    var offEl = info.querySelector('[data-off]');
    var notify = info.querySelector('[data-notify]');
    var drop = info.querySelector('[data-pricedrop]');
    var deal = document.querySelector('[data-deal]');
    var cur = null;

    function fmt(t, a, b) {
      return String(t || '').replace('%d', a).replace('%s', b);
    }

    function byId(id) {
      for (var i = 0; i < rows.length; i++) if (rows[i].id === id) return rows[i];
      return null;
    }

    function matches(r, sel, upTo) {
      for (var i = 0; i < upTo; i++) if ((r.options || {})[names[i]] !== sel[names[i]]) return false;
      return true;
    }

    function pick(name, value) {
      var idx = names.indexOf(name);
      var sel = {};
      names.forEach(function (n) { sel[n] = cur.options[n]; });
      sel[name] = value;
      var best = null;
      var score = -1;
      rows.forEach(function (r) {
        if (!matches(r, sel, idx + 1)) return;
        var sc = r.in_stock ? 100 : 0;
        for (var i = idx + 1; i < names.length; i++) if ((r.options || {})[names[i]] === sel[names[i]]) sc++;
        if (sc > score) { score = sc; best = r; }
      });
      if (best) apply(best, false);
    }

    function text2(el, t) {
      if (!el) return;
      var n = el.querySelector('.num');
      (n || el).textContent = t;
    }

    function photo(r) {
      var p = r.photo;
      if (!p) return;
      var thumb = p.view !== null && p.view !== undefined ? document.querySelector('.gallery [data-view="' + p.view + '"]') : null;
      if (thumb) { thumb.click(); return; }
      var img = document.querySelector('.gallery [data-main] img');
      if (img && p.normal) img.src = p.normal;
    }

    /* Add to cart, Buy now and the phone bar follow the picked variant. */
    var qty = 1;
    var qtyOut = document.querySelector('[data-buy] [data-qty-value]');
    var stickyPrice = document.querySelector('[data-sticky-price]');
    var stickyVar = document.querySelector('[data-sticky-variant]');

    /* The phone bar waits until the real buy row has scrolled up out of view, so it never covers the options. */
    var stickyBar = document.querySelector('[data-sticky-buy]');
    var buyRow = document.querySelector('[data-buy]');
    if (stickyBar && buyRow && 'IntersectionObserver' in window) {
      stickyBar.classList.add('wait');
      new IntersectionObserver(function (entries) {
        var e = entries[entries.length - 1];
        stickyBar.classList.toggle('wait', e.isIntersecting || e.boundingClientRect.top > 0);
      }).observe(buyRow);
    }

    function setQty(n) {
      var max = cur && cur.low > 0 ? Math.min(20, cur.low) : 20;
      qty = Math.max(1, Math.min(max, n));
      if (qtyOut) qtyOut.textContent = String(qty);
    }

    function syncBuy(r) {
      document.querySelectorAll('[data-add], [data-buy-now]').forEach(function (b) { b.disabled = !r.in_stock; });
      if (stickyPrice) stickyPrice.textContent = r.price;
      if (stickyVar) stickyVar.textContent = names.map(function (n) { return (r.options || {})[n] || ''; }).filter(Boolean).join(' · ');
      setQty(qty);
    }

    function added(btn) {
      var label = btn.querySelector('[data-add-label]');
      if (!label) return;
      if (btn.dataset.was === undefined) btn.dataset.was = label.textContent;
      clearTimeout(+btn.dataset.timer || 0);
      btn.classList.add('done');
      label.textContent = text.added || 'Added';
      btn.dataset.timer = String(setTimeout(function () {
        btn.classList.remove('done');
        label.textContent = btn.dataset.was;
        delete btn.dataset.was;
      }, 1400));
    }

    document.addEventListener('click', function (e) {
      var t = e.target.closest ? e.target : null;
      if (!t || !cur) return;
      if (t.closest('[data-qty-minus]')) { setQty(qty - 1); return; }
      if (t.closest('[data-qty-plus]')) { setQty(qty + 1); return; }
      var addBtn = t.closest('[data-add]');
      var nowBtn = t.closest('[data-buy-now]');
      if (!addBtn && !nowBtn) return;
      if (!cur.in_stock) return;
      if (!window.aisleCart) { toast(text.failed); return; }
      if (nowBtn) {
        window.aisleCart.add(cur.id, qty, { open: false });
        var drawer = document.querySelector('[data-cart-drawer]');
        location.href = (drawer && drawer.dataset.checkout) || '/';
        return;
      }
      if (window.aisleCart.add(cur.id, qty)) added(addBtn);
    });

    function apply(r, initial) {
      var changedPhoto = !cur || !cur.photo || !r.photo || cur.photo.id !== r.photo.id;
      cur = r;
      info.dataset.variant = String(r.id);
      info.dispatchEvent(new CustomEvent('aisle:variant'));
      groups.forEach(function (g, gi) {
        var val = (r.options || {})[g.name] || '';
        if (g.label) g.label.textContent = val;
        g.buttons.forEach(function (b) {
          b.setAttribute('aria-pressed', String(b.dataset.value === val));
          var ok = gi === 0 || rows.some(function (x) {
            if (!x.in_stock || (x.options || {})[g.name] !== b.dataset.value) return false;
            for (var i = 0; i < gi; i++) if ((x.options || {})[names[i]] !== (r.options || {})[names[i]]) return false;
            return true;
          });
          b.classList.toggle('sold', !ok);
          if (b.querySelector('img, span')) {
            b.setAttribute('aria-label', b.dataset.value);
          } else if (!ok) {
            b.setAttribute('aria-label', fmt(text.sold, '', b.dataset.value));
          } else {
            b.removeAttribute('aria-label');
          }
        });
      });

      text2(priceEl, r.price);
      if (wasEl) { wasEl.hidden = !r.off; wasEl.textContent = r.was; }
      if (offEl) { offEl.hidden = !r.off; offEl.textContent = fmt(text.off, r.off, ''); }
      if (deal) { deal.hidden = !r.off; deal.textContent = '−' + r.off + '%'; }

      if (stock) {
        var last = names.length ? (r.options || {})[names[names.length - 1]] : '';
        var cls = 'ok';
        var msg = text.ok;
        var icon = 'check';
        if (!r.in_stock) { cls = 'out'; msg = text.out; icon = 'box'; }
        else if (r.low > 0) { cls = 'lowq'; icon = 'box'; msg = last ? fmt(text.lowin, r.low, last) : fmt(text.low, r.low, ''); }
        stock.className = 'stock ' + cls;
        var use = stock.querySelector('use');
        if (use) use.setAttribute('href', '#i-' + icon);
        text2(stock, msg);
      }
      if (notify) notify.hidden = r.in_stock;
      syncBuy(r);

      if (!initial && changedPhoto) photo(r);
      if (!initial || location.hash) {
        try { history.replaceState(null, '', '#v=' + r.id); } catch (e) { /* hash is a nicety */ }
      }
    }

    info.addEventListener('click', function (e) {
      var b = e.target.closest('.opt button[data-value]');
      if (!b || b.disabled || !cur) return;
      pick(b.closest('.opt').dataset.option, b.dataset.value);
    });

    var start = null;
    var m = /(?:^|[#&])v=(\d+)/.exec(location.hash);
    if (m) start = byId(+m[1]);
    var first = start;
    if (!first) {
      for (var i = 0; i < rows.length; i++) if (rows[i].in_stock) { first = rows[i]; break; }
    }
    apply(first || rows[0], !start);
    if (start) photo(start);

    var listing = +info.dataset.listing || 0;

    function apiConfig() {
      var meta = document.querySelector('meta[name="shopclass-api"]');
      if (!meta) return null;
      try {
        var d = JSON.parse(meta.getAttribute('content') || '');
        return d && d.url ? d : null;
      } catch (e) { return null; }
    }

    function signedIn() {
      var u = document.querySelector('meta[name="aisle-user"]');
      return !!(u && u.getAttribute('content'));
    }

    function alertMe(kind) {
      if (!signedIn()) {
        location.href = info.dataset.login || '/';
        return;
      }
      var c = apiConfig();
      if (!c || !listing || !cur) { toast(text.failed); return; }
      var btn = kind === 'stock' ? notify : drop;
      if (btn.getAttribute('aria-busy') === 'true') return;
      btn.setAttribute('aria-busy', 'true');
      var headers = { Accept: 'application/json', 'Content-Type': 'application/json' };
      if (c.header && c.token) headers[c.header] = c.token;
      fetch(String(c.url).replace(/\/+$/, '') + '/account/alerts', {
        method: 'POST',
        headers: headers,
        credentials: 'same-origin',
        body: JSON.stringify({ product: { listing: listing, variant: cur.id, kind: kind } })
      }).then(function (res) {
        if (res.ok) { toast(res.status === 201 ? text.saved : text.exists); return; }
        return res.json().catch(function () { return null; }).then(function (body) {
          var code = body && body.error && body.error.code;
          toast(res.status === 409 || code === 'exists' ? text.exists : text.failed);
        });
      }).catch(function () { toast(text.failed); }).then(function () { btn.removeAttribute('aria-busy'); });
    }

    if (notify) notify.addEventListener('click', function () { alertMe('stock'); });
    if (drop) drop.addEventListener('click', function () { alertMe('price'); });
  }

  function load() {
    try {
      var list = JSON.parse(localStorage.getItem(KEY) || '[]');
      return Array.isArray(list) ? list : [];
    } catch (e) { return []; }
  }

  function recent() {
    var host = document.querySelector('[data-product]');
    if (!host) return;
    var cur = null;
    try { cur = JSON.parse(host.dataset.product); } catch (e) { return; }
    if (!cur || !cur.id) return;

    var list = load().filter(function (p) { return p && p.id !== cur.id; });
    var others = list.slice(0, 6);
    try {
      localStorage.setItem(KEY, JSON.stringify([cur].concat(list).slice(0, 6)));
    } catch (e) { /* storage blocked: the rail just stays empty */ }

    var section = document.querySelector('[data-recent]');
    var rail = document.querySelector('[data-recent-list]');
    if (!section || !rail || !others.length) return;

    others.forEach(function (p) {
      if (!p.url || !/^(https?:)?\/\//.test(p.url) && p.url.charAt(0) !== '/') return;
      var card = document.createElement('article');
      card.className = 'card';
      var ph = document.createElement('div');
      ph.className = 'ph';
      if (p.photo) {
        var im = document.createElement('img');
        im.src = p.photo;
        im.alt = '';
        im.loading = 'lazy';
        ph.appendChild(im);
      }
      var a = document.createElement('a');
      a.className = 't';
      a.href = p.url;
      a.textContent = p.title || '';
      var price = document.createElement('div');
      price.className = 'price';
      var b = document.createElement('b');
      var n = document.createElement('span');
      n.className = 'num';
      n.textContent = p.price || '';
      b.appendChild(n);
      price.appendChild(b);
      card.appendChild(ph);
      card.appendChild(a);
      if (p.price) card.appendChild(price);
      rail.appendChild(card);
    });
    if (rail.children.length) section.hidden = false;
  }

  /* The PIN box: asks GET cart/quote for the delivery date. Nothing is sent until Check is clicked or a saved PIN exists. */
  function deliver() {
    var box = document.querySelector('[data-deliver]');
    var info = document.querySelector('.info[data-listing]');
    if (!box || !info) return;
    var T = {};
    try { T = JSON.parse(box.dataset.i18n || '{}'); } catch (e) { /* defaults below */ }
    var form = box.querySelector('[data-deliver-form]');
    var input = form.elements.pin;
    var errEl = box.querySelector('[data-deliver-err]');
    var rowsEl = box.querySelector('[data-deliver-rows]');
    var country = box.dataset.country || '';
    var KEY = 'aisle-pin';
    var ctl = null;
    var seq = 0;
    var checked = false;

    function el(tag, cls, text) {
      var n = document.createElement(tag);
      if (cls) n.className = cls;
      if (text !== undefined) n.textContent = text;
      return n;
    }

    function icon(name) {
      var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.setAttribute('class', 'ico');
      svg.setAttribute('aria-hidden', 'true');
      var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
      use.setAttribute('href', '#i-' + name);
      svg.appendChild(use);
      return svg;
    }

    function amountText(a) {
      if (a && typeof a === 'object') return String(a.formatted || a.amount || '');
      return a === undefined || a === null ? '' : String(a);
    }

    function amountNum(a) {
      var n = parseFloat(a && typeof a === 'object' ? a.amount : a);
      return isFinite(n) ? n : 0;
    }

    function valid(pin) {
      return country === 'IN' ? /^\d{6}$/.test(pin) : /^[A-Za-z0-9 -]{3,10}$/.test(pin);
    }

    function show(msg) {
      errEl.textContent = msg || '';
      errEl.hidden = !msg;
      input.toggleAttribute('aria-invalid', !!msg);
    }

    function row(ic, lead, strong, sub) {
      var r = el('div', 'row');
      r.appendChild(icon(ic));
      var d = el('div');
      var parts = String(lead).split('%s');
      d.appendChild(document.createTextNode(parts[0]));
      if (strong) d.appendChild(el('b', '', strong));
      if (parts.length > 1) d.appendChild(document.createTextNode(parts.slice(1).join('%s')));
      if (sub) d.appendChild(el('span', '', sub));
      r.appendChild(d);
      return r;
    }

    function paint(d) {
      rowsEl.textContent = '';
      var bad = (Array.isArray(d.problems) ? d.problems : []).filter(function (p) { return p && p.pointer === '/address/postcode'; })[0];
      if (bad) { show(String(bad.message || T.no_serve)); return; }
      if (d.needs_shipping === false) { box.hidden = true; return; }
      var methods = Array.isArray(d.shipping_methods) ? d.shipping_methods : [];
      if (!methods.length) { show(T.no_serve); return; }
      show('');
      var m = methods[0];
      var fo = d.free_over && typeof d.free_over === 'object' ? amountText(d.free_over.amount) : '';
      var sub = fo ? String(T.free_over).replace('%s', fo) : '';
      if (m.eta) {
        rowsEl.appendChild(row('truck', T.get_by, String(m.eta), sub));
      } else {
        var cost = amountNum(m.amount) > 0 ? amountText(m.amount) : T.free;
        rowsEl.appendChild(row('truck', String(T.method).replace('%1$s', String(m.label || '')).replace('%2$s', cost), '', sub));
      }
      var cod = (Array.isArray(d.gateways) ? d.gateways : []).some(function (g) { return g && g.id === 'cod'; });
      if (cod) rowsEl.appendChild(row('cash', T.cod, '', ''));
    }

    function check() {
      var pin = input.value.trim();
      if (!valid(pin)) {
        if (ctl) ctl.abort();
        rowsEl.textContent = '';
        show(country === 'IN' ? T.pin_in : T.pin_zip);
        return;
      }
      var m = document.querySelector('meta[name="shopclass-api"]');
      var c = null;
      try { c = m ? JSON.parse(m.getAttribute('content') || '') : null; } catch (e) { c = null; }
      var variant = parseInt(info.dataset.variant || box.dataset.variant, 10);
      if (!c || !c.url || !variant) { box.hidden = true; return; }
      try { localStorage.setItem(KEY, pin); } catch (e) { /* works without it */ }
      if (ctl) ctl.abort();
      ctl = new AbortController();
      var mine = ++seq;
      checked = true;
      show('');
      var url = new URL(String(c.url).replace(/\/+$/, '') + '/cart/quote', location.href);
      url.searchParams.set('lines', variant + ':1');
      url.searchParams.set('postcode', pin);
      if (country) url.searchParams.set('country', country);
      var headers = { Accept: 'application/json' };
      if (c.header && c.token) headers[c.header] = c.token;
      fetch(url.toString(), { headers: headers, signal: ctl.signal, credentials: 'same-origin' }).then(function (r) {
        if (!r.ok) throw new Error('status ' + r.status);
        return r.json();
      }).then(function (body) {
        if (mine !== seq) return;
        var d = body && (body.data || body);
        if (!d || !Array.isArray(d.lines)) throw new Error('bad quote');
        paint(d);
      }).catch(function (err) {
        if (mine !== seq || (err && err.name === 'AbortError')) return;
        box.hidden = true;
      });
    }

    form.addEventListener('submit', function (e) { e.preventDefault(); check(); });
    input.addEventListener('input', function () { show(''); });
    info.addEventListener('aisle:variant', function () { if (checked) check(); });

    var saved = '';
    try { saved = localStorage.getItem(KEY) || ''; } catch (e) { saved = ''; }
    if (saved) {
      input.value = saved;
      if (valid(saved)) check();
    }
  }

  gallery();
  pickers();
  deliver();
  share();
  recent();
})();
