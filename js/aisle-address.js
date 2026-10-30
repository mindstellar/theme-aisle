/*
 * Address fields shared by checkout and the account address book. State is a list of the
 * country's regions when the shop has them, else free text; the postcode label follows the country.
 */
(function () {
  'use strict';

  var cache = {};

  function apiConfig() {
    var m = document.querySelector('meta[name="shopclass-api"]');
    if (!m) return null;
    try {
      var d = JSON.parse(m.getAttribute('content') || '');
      return d && d.url && d.token ? d : null;
    } catch (e) { return null; }
  }

  function option(text) {
    var o = document.createElement('option');
    o.value = text;
    o.textContent = text;
    return o;
  }

  function regions(cc) {
    if (cache[cc]) return cache[cc];
    var c = apiConfig();
    if (!c || !/^[A-Z]{2}$/.test(cc)) return Promise.resolve([]);
    var headers = { Accept: 'application/json' };
    headers[c.header] = c.token;
    cache[cc] = fetch(String(c.url).replace(/\/+$/, '') + '/countries/' + cc + '/regions', { headers: headers, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : { data: [] }; })
      .then(function (j) {
        return (Array.isArray(j && j.data) ? j.data : []).map(function (x) { return String(x && x.name || ''); }).filter(Boolean);
      })
      .catch(function () { delete cache[cc]; return []; });
    return cache[cc];
  }

  /* A value the list lacks, such as a saved address's state, is kept as its own option. */
  function keepState(sel, v) {
    if (!v) { sel.value = ''; return; }
    var hit = Array.prototype.filter.call(sel.options, function (o) { return o.value.toLowerCase() === v.toLowerCase(); })[0];
    if (!hit) { hit = option(v); sel.appendChild(hit); }
    sel.value = hit.value;
  }

  function country(form) {
    var f = form.elements.country;
    return f ? String(f.value).trim().toUpperCase() : '';
  }

  /* Swaps the form's state field for a list or a text box. Resolves true when its value changed. */
  function paintState(form, pick) {
    var cc = country(form);
    return regions(cc).then(function (names) {
      var old = form.elements.state;
      if (!old || country(form) !== cc) return false;
      var now = String(old.value).trim();
      var next;
      if (names.length) {
        next = document.createElement('select');
        var blank = option(pick);
        blank.value = '';
        next.appendChild(blank);
        names.forEach(function (n) { next.appendChild(option(n)); });
        keepState(next, now);
      } else if (old.tagName === 'SELECT') {
        next = document.createElement('input');
        next.type = 'text';
        next.value = now;
      } else {
        return false;
      }
      next.name = 'state';
      next.required = true;
      next.setAttribute('autocomplete', 'address-level1');
      ['id', 'class', 'maxlength', 'aria-invalid', 'aria-describedby'].forEach(function (a) {
        if (old.hasAttribute(a) && !(a === 'maxlength' && next.tagName === 'SELECT')) next.setAttribute(a, old.getAttribute(a));
      });
      old.replaceWith(next);
      return String(next.value).trim() !== now;
    });
  }

  function postcodeLabel(form, T) {
    return country(form) === 'IN' ? T.pin_label : T.zip_label;
  }

  window.aisleAddress = { keepState: keepState, paintState: paintState, postcodeLabel: postcodeLabel };

  /* The account address book: core draws the form, this keeps it in step with checkout. */
  var book = document.getElementById('oe-addr-form');
  if (!book) return;
  var T = { pin_label: 'PIN code', zip_label: 'Postcode', state_pick: 'Choose…' };
  try {
    var raw = document.getElementById('aisle-address-i18n');
    if (raw) Object.assign(T, JSON.parse(raw.textContent || '{}'));
  } catch (e) { /* keep defaults */ }
  var label = book.querySelector('label[for="oe-addr-postcode"]');
  function paint() {
    if (label) label.textContent = postcodeLabel(book, T);
    paintState(book, T.state_pick);
  }
  book.addEventListener('change', function (e) { if (e.target && e.target.name === 'country') paint(); });
  paint();
})();
