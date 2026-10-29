/*
 * Readable copy of the inline script in common/head.php, which is what runs.
 * It applies the visitor's saved scheme before first paint so the page never flashes.
 * "system" and an empty value keep the owner default already set on <html>.
 */
(function () {
  try {
    var v = localStorage.getItem('aisle-scheme');
    if (v === 'light' || v === 'dark') {
      document.documentElement.setAttribute('data-theme', v);
    } else if (v === 'system') {
      document.documentElement.removeAttribute('data-theme');
    }
  } catch (e) {}
})();
