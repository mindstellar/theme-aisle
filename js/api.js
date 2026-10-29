/*
 * Aisle API helper. Reads the shopclass-api meta tag and sends the page token with each request.
 * aisleApi(path, query, signal) resolves to the parsed JSON, or null on any failure.
 */
(function () {
  'use strict';

  var cfg = null;

  function config() {
    if (cfg) return cfg;
    var m = document.querySelector('meta[name="shopclass-api"]');
    if (!m) return null;
    try {
      var d = JSON.parse(m.getAttribute('content') || '');
      if (d && d.url) cfg = { url: String(d.url).replace(/\/+$/, ''), token: d.token || '', header: d.header || '' };
    } catch (e) { /* bad meta: no API */ }
    return cfg;
  }

  window.aisleApi = function (path, query, signal) {
    var c = config();
    if (!c) return Promise.resolve(null);
    var url = new URL(c.url + '/' + String(path).replace(/^\/+/, ''), location.href);
    Object.keys(query || {}).forEach(function (k) {
      if (query[k] !== undefined && query[k] !== null && query[k] !== '') url.searchParams.set(k, query[k]);
    });
    var headers = { Accept: 'application/json' };
    if (c.header && c.token) headers[c.header] = c.token;
    return fetch(url.toString(), { headers: headers, signal: signal, credentials: 'omit' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .catch(function () { return null; });
  };
})();
