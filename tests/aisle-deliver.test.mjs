// Runs the delivery box check in js/aisle-product.js against a stub DOM. Use: node tests/aisle-deliver.test.mjs
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const code = readFileSync(new URL('../js/aisle-product.js', import.meta.url), 'utf8');

// Submits the box with a typed postcode and returns the error text it shows ('' when it passes).
function submit(dataset, typed) {
  const err = { textContent: '', hidden: true };
  const input = { value: typed, toggleAttribute() {}, addEventListener() {} };
  let onSubmit = null;
  const form = { elements: { pin: input }, addEventListener(t, f) { if (t === 'submit') onSubmit = f; } };
  const rows = { textContent: '' };
  const box = {
    dataset: { i18n: JSON.stringify({ pin_zip: 'Enter a valid postcode.' }), ...dataset },
    hidden: false,
    querySelector: (s) => ({ '[data-deliver-form]': form, '[data-deliver-err]': err, '[data-deliver-rows]': rows }[s] || null),
  };
  const info = { dataset: { listing: '1', variant: '5' }, addEventListener() {} };
  const doc = {
    readyState: 'complete',
    querySelector: (s) => (s === '[data-deliver]' ? box : s === '.info[data-listing]' ? info : null),
    querySelectorAll: () => [],
    getElementById: () => null,
    createElement: () => ({ setAttribute() {}, appendChild() {} }),
    addEventListener() {},
  };
  const win = { addEventListener() {} };
  win.window = win;
  vm.runInNewContext(code, {
    window: win, document: doc, location: { href: 'http://x/', hash: '', search: '' },
    localStorage: { getItem: () => null, setItem() {} }, AbortController, setTimeout: () => 0, clearTimeout() {},
  });
  onSubmit({ preventDefault() {} });
  return err.textContent;
}

const GB = { country: 'GB', postcodePattern: '^[A-Z]{1,2}\\d[A-Z\\d]? ?\\d[A-Z]{2}$' };
const IN = { country: 'IN', postcodePattern: '^\\d{6}$' };

assert.equal(submit(GB, 'SW1A 1AA'), '', 'a GB postcode passes');
assert.equal(submit(GB, 'sw1a 1aa'), '', 'the pattern ignores case');
assert.notEqual(submit(GB, '123'), '', 'a GB box rejects 123');
assert.equal(submit(IN, '682001'), '', 'an IN pin passes');
assert.notEqual(submit(IN, '68200'), '', 'an IN pin needs 6 digits');
assert.equal(submit({}, 'AB 123'), '', 'no pattern: loose check passes');
assert.notEqual(submit({}, '1'), '', 'no pattern: too short fails');
console.log('ok');
