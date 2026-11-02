// Checks the checkout's address rules in js/aisle-checkout.js. Use: node tests/aisle-checkout-address.test.mjs
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const code = readFileSync(new URL('../js/aisle-checkout.js', import.meta.url), 'utf8');
const win = {};
win.window = win;
vm.runInNewContext(code, { window: win, document: { querySelector: () => null, getElementById: () => null } });
const T = { required: 'This field is required.' };
const field = (o) => ({ value: '', required: false, disabled: false, validationMessage: '', ...o });
const check = (o) => win.aisleAddressFieldProblem(field(o), T);

assert.equal(check({ value: 'SW1A 1AA', required: true }), '', 'a UK postcode passes');
assert.equal(check({ value: '', required: false, disabled: true }), '', 'a state the country does not use is skipped');
assert.equal(check({ value: '', required: true, disabled: true }), '', 'a disabled field is never required');
assert.equal(check({ value: '', required: false }), '', 'an empty optional city passes');
assert.equal(check({ value: '+44 20 7946 0000', required: true }), '', 'an international phone passes');
assert.equal(check({ value: '12345', required: true }), '', 'a short phone passes, the server decides');
assert.equal(check({ value: '', required: true }), T.required, 'an empty required field is flagged');
assert.equal(check({ value: '123', required: true, validationMessage: 'Enter a valid postcode.' }), 'Enter a valid postcode.', 'core\'s postcode flag shows');
console.log('aisle-checkout-address ok');
