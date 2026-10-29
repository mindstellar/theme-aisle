// Runs js/aisle-cart.js price notices against a stub DOM. Use: node tests/aisle-cart-price.test.mjs
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const code = readFileSync(new URL('../js/aisle-cart.js', import.meta.url), 'utf8');

function boot() {
  const store = {};
  const node = () => ({ className: '', textContent: '', children: [], setAttribute() {}, appendChild(c) { this.children.push(c); return c; } });
  const doc = {
    readyState: 'complete',
    querySelector: () => null,
    querySelectorAll: () => [],
    getElementById: () => null,
    createElement: node,
    addEventListener() {},
    dispatchEvent() { return true; },
  };
  const win = { addEventListener() {}, CustomEvent: class {} };
  win.window = win;
  vm.runInNewContext(code, {
    window: win,
    document: doc,
    location: { hash: '', href: 'http://x/', pathname: '/', search: '' },
    CustomEvent: win.CustomEvent,
    setTimeout: () => 0,
    clearTimeout() {},
    AbortController,
    localStorage: { getItem: (k) => (k in store ? store[k] : null), setItem: (k, v) => { store[k] = String(v); } },
  });
  return win.aisleCart;
}

const line = (amount) => ({ variant: 7, listing: { title: 'Lamp' }, unit: { amount, formatted: '$' + amount } });

const cart = boot();
const notes = (l) => JSON.parse(JSON.stringify(cart.priceNotes(l)));
assert.deepEqual(notes([line('10.00')]), [], 'the first quote only records the price');
assert.deepEqual(notes([line('10.00')]), [], 'an unchanged price is quiet');
assert.deepEqual(notes([line('12.50')]), ['The price of Lamp changed from $10.00 to $12.50.']);
assert.equal(notes([line('12.50')]).length, 1, 'the notice stays until the shopper accepts it');
assert.equal(JSON.stringify(cart.priceNotes([])), JSON.stringify([]), 'a line that left the cart is forgotten');
assert.deepEqual(notes([line('12.50')]), []);

const stock = boot();
stock.add(7, 5, { open: false });
stock.add(8, 2, { open: false });
const hit = stock.clamp({ lines: [{ variant: 7, stock_left: 3 }, { variant: 8, stock_left: null }] });
assert.equal(hit, true, 'a line over the stock is clamped');
const now = JSON.parse(JSON.stringify(stock.lines()));
assert.equal(now.find((l) => l.v === 7).q, 3, 'it drops to the stock left');
assert.equal(now.find((l) => l.v === 8).q, 2, 'other lines are untouched');
assert.equal(stock.clamp({ lines: [{ variant: 7, stock_left: 3 }] }), false, 'a line at the cap stays');
console.log('PASS  aisle-cart price notices and stock clamp');
