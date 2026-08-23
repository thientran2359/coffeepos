'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..', '..');
const source = fs.readFileSync(path.join(root, 'assets/js/sync/protocol.js'), 'utf8');
const window = { CoffeePOS: {}, URL: URL };
window.window = window;

vm.runInNewContext(source, { window, URL, Date, Math, Number, Object, Array, String, RegExp });

const protocol = window.CoffeePOS.sync.protocol;
const safe = protocol.safeCustomerCart({
    pos_session_id: '1234567890abcdef',
    revision: 4,
    customer: {
        mode: 'member',
        is_guest: false,
        customer_id: 91,
        display_name: 'Lan',
        phone: '0353123250',
        phone_masked: '0353***250',
        email: 'private@example.test',
        membership: { tier_label: 'Gold', unsafe: '<b>secret</b>' }
    },
    items: []
});

assert.strictEqual(safe.customer.mode, 'member');
assert.strictEqual(safe.customer.display_name, 'Lan');
assert.strictEqual(safe.customer.phone_masked, '0353***250');
assert.strictEqual(safe.customer.membership.tier_label, 'Gold');
assert.strictEqual(Object.prototype.hasOwnProperty.call(safe.customer, 'phone'), false);
assert.strictEqual(Object.prototype.hasOwnProperty.call(safe.customer, 'email'), false);
assert.strictEqual(Object.prototype.hasOwnProperty.call(safe.customer, 'customer_id'), false);
assert.strictEqual(Object.prototype.hasOwnProperty.call(safe.customer.membership, 'unsafe'), false);

const guest = protocol.safeCustomer({ mode: 'guest', display_name: 'Private', phone_masked: 'should-not-show' });
assert.strictEqual(guest.mode, 'guest');
assert.strictEqual(guest.display_name, 'Guest');
assert.strictEqual(guest.phone_masked, '');

console.log('Phase 08 privacy scenarios passed.');
