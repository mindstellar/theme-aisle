/*
 * Aisle orders. Buy again fills the cart; Pay now asks the API how to pay again; Cancel asks twice,
 * then posts to the API with the page token.
 */
(function () {
  'use strict';

  var T = {
    cancelled: 'Cancelled',
    cancel_ok: 'Order cancelled',
    cancel_no: 'We could not cancel this order. It may have been paid already.',
    again_ok: 'Items added to your cart',
    again_no: 'Those items are no longer available',
    pay_no: 'We could not start the payment. Try again in a moment.'
  };
  try {
    var raw = document.getElementById('aisle-orders-i18n');
    if (raw) Object.assign(T, JSON.parse(raw.textContent || '{}'));
  } catch (e) { /* keep defaults */ }

  function say(text) {
    if (typeof window.aisleToast === 'function') window.aisleToast(text);
  }

  function apiConfig() {
    var m = document.querySelector('meta[name="shopclass-api"]');
    if (!m) return null;
    try {
      var d = JSON.parse(m.getAttribute('content') || '');
      return d && d.url && d.token && d.header ? d : null;
    } catch (e) { return null; }
  }

  /* Buy again: [{v, q}] with whole numbers only, so nothing else reaches the cart. */
  function buyAgain(btn) {
    var list = [];
    try { list = JSON.parse(btn.getAttribute('data-buy-again') || '[]'); } catch (e) { /* bad value */ }
    var lines = (Array.isArray(list) ? list : []).filter(function (l) {
      return l && Number.isInteger(l.v) && l.v > 0 && Number.isInteger(l.q) && l.q > 0 && l.q <= 20;
    });
    if (!lines.length || !window.aisleCart) { say(T.again_no); return; }
    lines.forEach(function (l) { window.aisleCart.add(l.v, l.q); });
    say(T.again_ok);
    if (typeof window.aisleCart.open === 'function') window.aisleCart.open();
  }

  /* The card after a cancel: the pill says Cancelled and the tracker and actions go. */
  function markCancelled(card) {
    card.setAttribute('data-status', 'cancelled');
    var pill = card.querySelector('[data-status-pill]');
    if (pill) {
      pill.className = 'status off';
      var label = pill.querySelector('span');
      if (label) label.textContent = T.cancelled;
    }
    var track = card.querySelector('[data-track]');
    if (track) track.remove();
    card.querySelectorAll('[data-cancel-order], [data-pay-order]').forEach(function (b) { b.remove(); });
  }

  function safeUrl(u) {
    if (typeof u !== 'string' || u === '') return '';
    try {
      var p = new URL(u, location.href);
      return p.protocol === 'http:' || p.protocol === 'https:' ? p.href : '';
    } catch (e) { return ''; }
  }

  function runScripts(box) {
    box.querySelectorAll('script').forEach(function (old) {
      var s = document.createElement('script');
      Array.prototype.forEach.call(old.attributes, function (a) { s.setAttribute(a.name, a.value); });
      s.textContent = old.textContent;
      old.parentNode.replaceChild(s, old);
    });
  }

  /* Pay now: a redirect goes to the gateway; markup is the gateway's own, printed under the card. */
  function pay(btn) {
    var id = btn.getAttribute('data-pay-order') || '';
    var c = apiConfig();
    if (!/^\d{1,10}$/.test(id) || !c) { say(T.pay_no); return; }
    btn.disabled = true;
    var headers = { Accept: 'application/json' };
    headers[c.header] = c.token;
    fetch(String(c.url).replace(/\/+$/, '') + '/account/orders/' + id + '/pay', { method: 'POST', headers: headers, credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok) throw new Error('status ' + r.status);
        return r.json();
      })
      .then(function (j) {
        var next = (j && j.next) || null;
        if (next && next.kind === 'redirect' && safeUrl(next.payload)) {
          location.assign(safeUrl(next.payload));
          return;
        }
        var card = btn.closest('[data-order]');
        var box = card ? card.querySelector('[data-pay-intent]') : null;
        if (!box || !next || next.kind !== 'html' || typeof next.payload !== 'string') throw new Error('no next step');
        box.innerHTML = next.payload; /* the gateway's own markup, printed as given */
        box.hidden = false;
        runScripts(box);
        btn.disabled = false;
      })
      .catch(function () {
        btn.disabled = false;
        say(T.pay_no);
      });
  }

  function cancel(btn) {
    var id = btn.getAttribute('data-cancel-order') || '';
    if (!/^\d{1,10}$/.test(id)) return;
    var label = btn.querySelector('span') || btn;

    if (!btn.hasAttribute('data-armed')) {
      btn.setAttribute('data-armed', '');
      btn.setAttribute('data-was', label.textContent);
      label.textContent = btn.getAttribute('data-confirm') || label.textContent;
      setTimeout(function () {
        if (btn.isConnected && btn.hasAttribute('data-armed') && !btn.disabled) {
          btn.removeAttribute('data-armed');
          label.textContent = btn.getAttribute('data-was') || label.textContent;
        }
      }, 5000);
      return;
    }

    var c = apiConfig();
    if (!c) { say(T.cancel_no); return; }
    btn.disabled = true;
    var headers = { Accept: 'application/json' };
    headers[c.header] = c.token;
    fetch(String(c.url).replace(/\/+$/, '') + '/account/orders/' + id + '/cancel', { method: 'POST', headers: headers, credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok) throw new Error('status ' + r.status);
        var card = btn.closest('[data-order]');
        if (card) markCancelled(card);
        say(T.cancel_ok);
      })
      .catch(function () {
        btn.disabled = false;
        btn.removeAttribute('data-armed');
        label.textContent = btn.getAttribute('data-was') || label.textContent;
        say(T.cancel_no);
      });
  }

  document.addEventListener('click', function (e) {
    var t = e.target;
    if (!t || !t.closest) return;
    var again = t.closest('[data-buy-again]');
    if (again) { e.preventDefault(); buyAgain(again); return; }
    var payBtn = t.closest('[data-pay-order]');
    if (payBtn) { e.preventDefault(); pay(payBtn); return; }
    var del = t.closest('[data-cancel-order]');
    if (del) { e.preventDefault(); cancel(del); }
  });

  /* Straight from checkout with a gateway that draws its own form: open it now. */
  try {
    var here = new URL(location.href);
    if (here.searchParams.get('pay') === '1') {
      here.searchParams.delete('pay');
      history.replaceState(null, '', here.toString());
      var first = document.querySelector('[data-pay-order]');
      if (first) pay(first);
    }
  } catch (e) { /* the Pay now button still works */ }
})();
