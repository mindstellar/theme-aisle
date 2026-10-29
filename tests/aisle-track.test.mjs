// Runs js/aisle-track.js against a stub DOM. Use: node tests/aisle-track.test.mjs
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const code = readFileSync(new URL('../js/aisle-track.js', import.meta.url), 'utf8');

function boot(page, store = {}) {
  const listeners = {};
  const doc = {
    getElementById: (id) => (id === 'aisle-page' ? { textContent: JSON.stringify(page) } : null),
    addEventListener: (n, f) => { (listeners[n] = listeners[n] || []).push(f); },
    dispatchEvent: (e) => { (listeners[e.type] || []).forEach((f) => f(e)); return true; },
  };
  const win = {
    localStorage: { getItem: (k) => (k in store ? store[k] : null), setItem: (k, v) => { store[k] = String(v); } },
    CustomEvent: class { constructor(type, init) { this.type = type; this.detail = init && init.detail; } },
  };
  win.window = win;
  vm.runInNewContext(code, { window: win, document: doc });
  return { win, doc, store, fire: (type, detail) => doc.dispatchEvent(new win.CustomEvent(type, { detail })) };
}

// add_to_cart: an unchanged event is quiet, a changed line gives one event with the delta.
{
  const store = { 'aisle-cart': JSON.stringify([{ v: 7, q: 1 }]) };
  const page = { page: 'product', currency: 'INR', items: [{ item_id: '3', item_name: 'Lamp', price: 10, variant_id: 7, index: 0 }] };
  const { win, fire } = boot(page, store);
  assert.equal(win.aisleTrack.length, 1);
  assert.equal(win.aisleTrack[0].event, 'view_item');
  fire('aisle:cart', { lines: [{ v: 7, q: 1 }] });
  fire('aisle:cart', { lines: [{ v: 7, q: 3 }] });
  const adds = win.aisleTrack.filter((e) => e.event === 'add_to_cart');
  assert.equal(adds.length, 1);
  assert.equal(adds[0].items[0].quantity, 2);
  assert.equal(adds[0].items[0].item_name, 'Lamp');
  assert.equal(adds[0].value, 20);
  fire('aisle:cart', { lines: [] });
  assert.equal(win.aisleTrack.filter((e) => e.event === 'remove_from_cart')[0].items[0].quantity, 3);
}

// purchase fires once across two loads.
{
  const store = {};
  const page = { page: 'order', currency: 'INR', items: [], order: { transaction_id: '#1042', value: 5, items: [] } };
  const a = boot(page, store);
  const b = boot(page, store);
  assert.equal(a.win.aisleTrack.filter((e) => e.event === 'purchase').length, 1);
  assert.equal(b.win.aisleTrack.filter((e) => e.event === 'purchase').length, 0);
}

// Listing and search pages.
{
  const store = {};
  const page = { page: 'search', currency: 'INR', list: { id: 'all', name: 'All' }, search_term: 'lamp', items: [{ item_id: '1', item_name: 'A', price: 1, index: 0 }] };
  const b = boot(page, store);
  assert.equal(b.win.aisleTrack.map((e) => e.event).join(), 'view_item_list,search');
}

// A missing block does not throw.
{
  const { win } = boot({});
  assert.equal(win.aisleTrack.length, 0);
}

console.log('aisle-track: ok');
