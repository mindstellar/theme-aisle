// Runs js/aisle-checkout.js checkout-field rules on their own. Use: node tests/aisle-checkout-fields.test.mjs
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const code = readFileSync(new URL('../js/aisle-checkout.js', import.meta.url), 'utf8');
const win = {};
win.window = win;
vm.runInNewContext(code, { window: win, document: { querySelector: () => null, getElementById: () => null } });
const plain = (o) => JSON.parse(JSON.stringify(o));
const defs = [
  { id: 'tax_id', label: 'GSTIN', required: true, max: 15 },
  { id: 'po', label: 'PO number', required: false, max: 64 },
];

assert.deepEqual(plain(win.aisleFieldsBody(defs, { tax_id: ' 07AAAAA0000A1Z5 ', po: '', other: 'x' })), { tax_id: '07AAAAA0000A1Z5' }, 'trimmed, blank and unknown dropped');
assert.deepEqual(plain(win.aisleFieldsBody([], { tax_id: 'x' })), {}, 'no fields, nothing sent');
assert.equal(win.aisleFieldProblem(defs[0], '  ', { required: 'Required.' }), 'Required.', 'required and blank');
assert.equal(win.aisleFieldProblem(defs[1], '', { required: 'Required.' }), '', 'optional and blank');
assert.equal(win.aisleFieldProblem(defs[0], 'abc', { required: 'Required.' }), '', 'filled');

const order = { fields: [{ id: 'tax_id', label: 'GSTIN', value: '07AAAAA0000A1Z5' }, { id: 'gone', label: 'Old', value: 'z' }, { id: 'po', label: 'PO', value: 12 }] };
assert.deepEqual(plain(win.aisleFieldsPrefill(defs, order)), { tax_id: '07AAAAA0000A1Z5' }, 'only asked fields, only text');
assert.deepEqual(plain(win.aisleFieldsPrefill(defs, null)), {}, 'no earlier order');
assert.deepEqual(plain(win.aisleFieldsPrefill(defs, { fields: 'x' })), {}, 'bad shape');
console.log('ok');
