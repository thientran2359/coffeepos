'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..', '..');
const context = { window: { CoffeePOS: {} } };
vm.createContext(context);
['assets/js/state/operational-order-sort.js', 'assets/js/state/kds-store.js', 'assets/js/state/order-queue-store.js'].forEach(function (file) {
    vm.runInContext(fs.readFileSync(path.join(root, file), 'utf8'), context, { filename: file });
});

function assert(condition, message) {
    if (!condition) { throw new Error(message); }
}
function order(id, receivedAt, state) {
    return { id: id, received_at: receivedAt, kds: { state: state || 'new' } };
}

const state = context.window.CoffeePOS.state;
const newer = order(30, '2026-09-10T09:05:00Z');
const oldest = order(10, '2026-09-10T09:01:00Z');
const sameTimeHigherId = order(20, '2026-09-10T09:03:00Z');
const sameTimeLowerId = order(15, '2026-09-10T09:03:00Z');

const sorted = state.sortOperationalOrders([newer, sameTimeHigherId, oldest, sameTimeLowerId]);
assert(sorted.map(function (item) { return item.id; }).join(',') === '10,15,20,30', 'Shared FIFO comparator is not received_at then id.');

const kds = state.createKdsStore();
kds.accept({ orders: [newer, oldest], server_time: '2026-09-10T09:06:00Z' });
kds.replace(order(10, '2026-09-10T09:01:00Z', 'preparing'));
assert(kds.getState().orders.map(function (item) { return item.id; }).join(',') === '10,30', 'KDS transition moved the oldest order.');

const queue = state.createOrderQueueStore();
queue.accept({ orders: [newer, oldest] });
queue.replace(order(10, '2026-09-10T09:01:00Z', 'ready'));
assert(queue.getState().orders.map(function (item) { return item.id; }).join(',') === '10,30', 'Order Queue transition moved the oldest order.');

console.log('Phase 07 operational FIFO scenarios passed.');
