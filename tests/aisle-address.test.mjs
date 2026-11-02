// Checks that Aisle leaves the address rules to core. Use: node tests/aisle-address.test.mjs
import { readFileSync, existsSync } from 'node:fs';
import assert from 'node:assert/strict';

const read = (p) => readFileSync(new URL('../' + p, import.meta.url), 'utf8');
const php = read('cart/checkout.php');
const js = read('js/aisle-checkout.js');

assert.ok(!existsSync(new URL('../js/aisle-address.js', import.meta.url)), 'Aisle has no address script of its own');
assert.ok(!/aisle-address/.test(php + read('functions.php')), 'nothing loads it');
assert.ok(!/PIN code|Mobile/.test(php + js), 'no India-only wording');
assert.ok(!/inputmode="numeric"/.test(php), 'postcode is not numeric-only');
for (const name of ['line1', 'line2', 'city', 'state', 'postcode']) {
  assert.match(php, new RegExp('data-address-field="' + name + '"'), name + ' has a core wrapper');
}
assert.match(php, /<div data-address-fields>/, 'core reorders inside one box');
assert.match(php, /function_exists\('osc_address_form_script'\)/, 'the core script is feature-checked');
for (const name of ['postcode', 'city', 'state']) {
  assert.ok(!new RegExp('name="' + name + '"[^>]*\\brequired\\b').test(php), name + ' is not required in markup');
}
assert.ok(!/pinOk|paintPostcodeLabel|paintState/.test(js), 'no local postcode or state painting');
console.log('aisle-address ok');
