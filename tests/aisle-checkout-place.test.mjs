// The checkout places an order only from the form's submit. Use: node tests/aisle-checkout-place.test.mjs
import { readFileSync, readdirSync } from 'node:fs';
import assert from 'node:assert/strict';

const dir = new URL('../js/', import.meta.url);
const code = readFileSync(new URL('aisle-checkout.js', dir), 'utf8');

// Two functions of one name in the same scope: the later one silently replaces the earlier.
for (const f of readdirSync(dir).filter((n) => n.endsWith('.js'))) {
  const names = [...readFileSync(new URL(f, dir), 'utf8').matchAll(/^ {2}function ([\w$]+)\s*\(/gm)].map((m) => m[1]);
  const dup = names.filter((n, i) => names.indexOf(n) !== i);
  assert.deepEqual(dup, [], f + ' declares a function twice: ' + dup.join(', '));
}

const calls = [...code.matchAll(/(?<![\w$.])place\(\)/g)].map((m) => m.index);
const decl = code.indexOf('function place()');
const submit = code.indexOf("form.addEventListener('submit'");
const others = calls.filter((i) => i !== decl + 'function '.length);
assert.equal(others.length, 1, 'place() is called from one spot');
assert.ok(others[0] > submit && others[0] < code.indexOf('});', submit), 'that spot is the submit handler');
console.log('aisle-checkout-place ok');
