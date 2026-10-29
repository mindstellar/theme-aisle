/*
 * Answers the admin customiser while it shows this page in a frame. Only loaded in the preview.
 * It trusts messages from its own origin (and the origin named in the osc-customiser meta tag),
 * applies <html> attributes only from the lists printed on <html>, and writes text as text nodes; the hero heading's *word* becomes <em>.
 */
(function () {
  'use strict';

  var TYPE = 'osc-customiser';
  var root = document.documentElement;
  var meta = document.querySelector('meta[name="osc-customiser"]');
  var adminOrigin = '';
  if (meta) {
    try { adminOrigin = new URL(meta.getAttribute('content') || '', location.href).origin; } catch (e) { adminOrigin = ''; }
  }

  function trusted(origin) {
    return origin === location.origin && (adminOrigin === '' || adminOrigin === location.origin);
  }

  function allowed(attr) {
    if (!/^data-[a-z-]+$/.test(attr)) { return null; }
    var list = root.getAttribute('data-osc-allow-' + attr);
    return list === null ? null : list.split(' ');
  }

  function applyAttr(attr, value) {
    var list = allowed(attr);
    if (list === null || list.indexOf(value) === -1) { return; }
    if (value === '' || value === 'system') {
      root.removeAttribute(attr);
    } else {
      root.setAttribute(attr, value);
    }
  }

  function writeText(node, value) {
    var text = value.trim() === '' ? (node.getAttribute('data-default') || value) : value;
    if (node.tagName !== 'H1') {
      node.textContent = text;
      return;
    }
    var m = /\*(.+?)\*/.exec(text);
    var frag = document.createDocumentFragment();
    if (m) {
      var em = document.createElement('em');
      em.textContent = m[1];
      frag.appendChild(document.createTextNode(text.slice(0, m.index)));
      frag.appendChild(em);
      frag.appendChild(document.createTextNode(text.slice(m.index + m[0].length)));
    } else {
      frag.appendChild(document.createTextNode(text));
    }
    node.replaceChildren(frag);
  }

  function applyText(section, field, value) {
    var sec = findSection(section);
    var hit = false;
    var nodes = sec ? sec.querySelectorAll('[data-field]') : [];
    for (var i = 0; i < nodes.length; i++) {
      if (nodes[i].getAttribute('data-field') === field) { writeText(nodes[i], value); hit = true; }
    }
    return hit;
  }

  function findSection(id) {
    var nodes = document.querySelectorAll('[data-sec]');
    for (var i = 0; i < nodes.length; i++) {
      if (nodes[i].getAttribute('data-sec') === String(id)) { return nodes[i]; }
    }
    return null;
  }

  function select(id, name) {
    var current = document.querySelectorAll('[data-sec].is-selected');
    for (var i = 0; i < current.length; i++) { current[i].classList.remove('is-selected'); }
    var sec = findSection(id);
    if (!sec) { return; }
    sec.classList.add('is-selected');
    if (typeof name === 'string') { sec.setAttribute('data-sec-name', name); }
    sec.scrollIntoView({ block: 'nearest', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
  }

  window.addEventListener('message', function (event) {
    var d = event.data;
    if (!trusted(event.origin) || !d || typeof d !== 'object' || d.type !== TYPE) { return; }
    if (d.set && typeof d.set === 'object') {
      applyAttr(String(d.set.attr), String(d.set.value));
    }
    if (d.text && typeof d.text === 'object') {
      if (!applyText(d.text.section, String(d.text.field), String(d.text.value))) {
        window.parent.postMessage({ type: TYPE, missed: true }, location.origin);
      }
    }
    if (d.select !== undefined) {
      select(d.select, d.name);
    }
  });

  document.addEventListener('click', function (event) {
    var sec = event.target instanceof Element ? event.target.closest('[data-sec]') : null;
    if (!sec) { return; }
    event.preventDefault();
    window.parent.postMessage({ type: TYPE, picked: sec.getAttribute('data-sec') }, location.origin);
  }, true);

  document.addEventListener('submit', function (event) { event.preventDefault(); }, true);

  window.parent.postMessage({ type: TYPE, ready: true }, location.origin);
})();
