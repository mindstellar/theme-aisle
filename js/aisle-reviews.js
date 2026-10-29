/*
 * Aisle reviews: "Show more" appends the next page, the form posts a review with the page token.
 * Review text goes in through textContent only.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-reviews]');
  if (!root) return;

  var T = {};
  try { T = JSON.parse(root.dataset.i18n || '{}'); } catch (e) { /* defaults below */ }
  var listing = parseInt(root.dataset.listing, 10) || 0;
  var stars = root.dataset.stars !== '0';

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined) n.textContent = text;
    return n;
  }

  function apiConfig() {
    var meta = document.querySelector('meta[name="shopclass-api"]');
    if (!meta) return null;
    try {
      var d = JSON.parse(meta.getAttribute('content') || '');
      return d && d.url ? d : null;
    } catch (e) { return null; }
  }

  function headers(c, json) {
    var h = { Accept: 'application/json' };
    if (json) h['Content-Type'] = 'application/json';
    if (c.header && c.token) h[c.header] = c.token;
    return h;
  }

  function sameApi(c, url) {
    try {
      var a = new URL(url, location.href);
      var b = new URL(c.url, location.href);
      return a.origin === b.origin && a.pathname.indexOf(b.pathname.replace(/\/+$/, '') + '/') === 0;
    } catch (e) { return false; }
  }

  function starRow(n) {
    var wrap = el('span', 'stars');
    wrap.setAttribute('role', 'img');
    wrap.setAttribute('aria-label', String(T.rated || 'Rated %s out of 5').replace('%s', String(n)));
    for (var i = 1; i <= 5; i++) {
      var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.setAttribute('class', 'ico sm' + (i <= n ? ' on' : ''));
      svg.setAttribute('aria-hidden', 'true');
      var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
      use.setAttribute('href', '#i-star');
      svg.appendChild(use);
      wrap.appendChild(svg);
    }
    return wrap;
  }

  function item(c) {
    var art = el('article', 'rvitem');
    var rating = stars ? parseInt(c.rating, 10) || 0 : 0;
    var title = String(c.title || '').trim();
    var body = String(c.body || '').trim();
    if (!rating && !title && !body) return null;
    if (rating >= 1 || title) {
      var head = el('header');
      if (rating >= 1) head.appendChild(starRow(Math.min(rating, 5)));
      if (title) head.appendChild(el('b', '', title));
      art.appendChild(head);
    }
    if (body) {
      var p = el('p');
      body.split(/\r?\n/).forEach(function (line, i) {
        if (i) p.appendChild(document.createElement('br'));
        p.appendChild(document.createTextNode(line));
      });
      art.appendChild(p);
    }
    var foot = el('footer', '', String((c.author && c.author.name) || '').trim() || T.anon || '');
    var d = new Date(c.published_at || '');
    if (!isNaN(d.getTime())) {
      foot.appendChild(document.createTextNode(' · '));
      var t = el('time', '', d.toLocaleDateString(document.documentElement.lang || undefined, { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' }));
      t.setAttribute('datetime', d.toISOString());
      foot.appendChild(t);
    }
    art.appendChild(foot);
    return art;
  }

  var list = root.querySelector('[data-review-list]');

  var more = root.querySelector('[data-review-more]');
  if (more && list) {
    more.addEventListener('click', function () {
      var c = apiConfig();
      var next = more.dataset.next || '';
      if (!c || !next || !sameApi(c, next)) { more.hidden = true; return; }
      more.disabled = true;
      fetch(next, { headers: headers(c, false), credentials: 'omit' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (body) {
          more.disabled = false;
          if (!body || !Array.isArray(body.data)) return;
          body.data.forEach(function (c2) { if (c2 && typeof c2 === 'object') { var a = item(c2); if (a) list.appendChild(a); } });
          var n = body.links && typeof body.links.next === 'string' ? body.links.next : '';
          if (n) more.dataset.next = n; else more.hidden = true;
        })
        .catch(function () { more.disabled = false; });
    });
  }

  var form = root.querySelector('[data-review-form]');
  if (!form) return;
  var msg = form.querySelector('[data-review-msg]');
  var busy = false;

  function say(text, cls) {
    if (!msg) return;
    msg.textContent = text;
    msg.className = 'rv-msg' + (cls ? ' ' + cls : '');
  }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    if (busy) return;
    var picked = form.querySelector('input[name="rating"]:checked');
    var title = form.elements.title.value.trim();
    var body = form.elements.body.value.trim();
    if (!picked && !body) { say(T.pick || '', 'bad'); return; }
    var c = apiConfig();
    if (!c || !listing) { say(T.failed || '', 'bad'); return; }
    var payload = {};
    if (picked) payload.rating = parseInt(picked.value, 10);
    if (title) payload.title = title;
    if (body) payload.body = body;

    busy = true;
    say('');
    fetch(String(c.url).replace(/\/+$/, '') + '/listings/' + listing + '/comments', {
      method: 'POST',
      headers: headers(c, true),
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    }).then(function (res) {
      return res.json().catch(function () { return null; }).then(function (data) { return { res: res, data: data }; });
    }).then(function (out) {
      busy = false;
      if (out.res.ok) {
        var all = [].concat((out.data && out.data.warnings) || [], (out.data && out.data.meta && out.data.meta.warnings) || []);
        var pending = all.some(function (w) { return w && w.code === 'comment_pending'; });
        if (!pending && list && out.data && out.data.data) list.insertBefore(item(out.data.data), list.firstChild);
        say(pending ? T.pending : T.posted, 'ok');
        form.reset();
        return;
      }
      var code = out.data && (out.data.code || (out.data.error && out.data.error.code));
      say(out.res.status === 409 && code === 'already_reviewed' ? T.already : T.failed, 'bad');
    }).catch(function () { busy = false; say(T.failed || '', 'bad'); });
  });
})();
