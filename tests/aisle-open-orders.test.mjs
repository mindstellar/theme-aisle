// Runs js/aisle-checkout.js open-orders rules on their own. Use: node tests/aisle-open-orders.test.mjs
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const code = readFileSync(new URL('../js/aisle-checkout.js', import.meta.url), 'utf8');
const win = {};
win.window = win;
vm.runInNewContext(code, { window: win, document: { querySelector: () => null, getElementById: () => null } });
const T = {
  open_one: 'You have %1$d unpaid bank transfer order. You can hold %2$d at once.',
  open_many: 'You have %1$d unpaid bank transfer orders. You can hold %2$d at once.',
};
const plain = (o) => JSON.parse(JSON.stringify(o));
const notice = (open) => plain(win.aisleOpenOrdersNotice(open, T));

assert.deepEqual(notice({ count: 0, limit: 3 }), { text: '', blocking: false }, 'no open order, no line');
assert.deepEqual(notice(undefined), { text: '', blocking: false }, 'no figures, no line');
assert.deepEqual(notice({ count: 5, limit: 0 }), { text: '', blocking: false }, 'limit 0 means none');
assert.deepEqual(notice({ count: 2, limit: 3 }), { text: 'You have 2 unpaid bank transfer orders. You can hold 3 at once.', blocking: false });
assert.equal(notice({ count: 1, limit: 3 }).text, 'You have 1 unpaid bank transfer order. You can hold 3 at once.');
assert.equal(notice({ count: 3, limit: 3 }).blocking, true, 'at the limit it blocks');
assert.equal(notice({ count: 4, limit: 3 }).blocking, true);

const atLimit = { lines: [], open_orders: { count: 3, limit: 3 } };
assert.deepEqual(plain(win.aisleOpenOrdersFor(atLimit, 'cod', T)), { text: '', blocking: false }, 'cash on delivery at the limit: no line');
assert.deepEqual(plain(win.aisleOpenOrdersFor(atLimit, 'stripe', T)), { text: '', blocking: false }, 'a card gateway: no line');
assert.equal(win.aisleOpenOrdersFor(atLimit, 'offline', T).blocking, true, 'bank transfer at the limit blocks');
assert.equal(win.aisleOpenOrdersFor(null, 'offline', T).text, '', 'no quote yet, no line');

const ready = { busy: false, quote: atLimit, problems: false, gateway: 'offline', openBlock: false };
assert.equal(win.aisleCanPlace(ready), true, 'a clean quote with a gateway can be placed');
assert.equal(win.aisleCanPlace({ ...ready, openBlock: true }), false, 'the open-orders block disables Place order');
assert.equal(win.aislePlaceBlocked({ ...ready, openBlock: true }), true, 'and stops the submit');
assert.equal(win.aislePlaceBlocked(ready), false);
assert.equal(win.aisleCanPlace({ ...ready, gateway: '' }), false, 'no gateway, no button');
assert.equal(win.aisleCanPlace({ ...ready, busy: true }), false, 'not twice while placing');
assert.equal(win.aislePlaceBlocked({ ...ready, problems: true }), true, 'a cart problem stops the submit');
console.log('PASS  aisle-checkout open orders notice');
