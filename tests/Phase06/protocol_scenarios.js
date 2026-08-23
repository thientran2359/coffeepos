'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..', '..');
const protocolSource = fs.readFileSync(path.join(root, 'assets/js/sync/protocol.js'), 'utf8');
const channelSource = fs.readFileSync(path.join(root, 'assets/js/sync/channel.js'), 'utf8');

const channels = [];
class FakeBroadcastChannel {
	constructor(name) {
		this.name = name;
		this.closed = false;
		this.messages = [];
		channels.push(this);
	}
	postMessage(message) { this.messages.push(message); }
	close() { this.closed = true; }
	addEventListener(type, listener) { if (type === 'message') { this.listener = listener; } }
}

const storage = new Map();
const window = {
	CoffeePOS: {},
	BroadcastChannel: FakeBroadcastChannel,
	URL: URL,
	crypto: { randomUUID: () => '00000000-0000-4000-8000-000000000001' },
	sessionStorage: {
		getItem: (key) => storage.has(key) ? storage.get(key) : null,
		setItem: (key, value) => storage.set(key, String(value)),
	},
};
window.window = window;

vm.runInNewContext(protocolSource, { window, URL, Date, Math, Number, Object, Array, String, RegExp });
vm.runInNewContext(channelSource, { window, Date, Math, Number, Object, Array, String, RegExp, Set, Map });

const protocol = window.CoffeePOS.sync.protocol;
const createChannel = window.CoffeePOS.sync.createChannel;
assert(protocol, 'SyncProtocol must be registered on the shared CoffeePOS namespace.');
assert(createChannel, 'SyncChannel factory must be registered on the shared CoffeePOS namespace.');

const sessionId = '1234567890abcdef';
assert.strictEqual(protocol.channelName(sessionId), 'coffeepos:' + sessionId);
assert.strictEqual(protocol.validSessionId(sessionId), true);
assert.strictEqual(protocol.validSessionId('short'), false);

const envelope = {
	version: 1,
	message_id: '00000000-0000-4000-8000-000000000099',
	type: 'display.ready',
	timestamp: new Date().toISOString(),
	pos_session_id: sessionId,
	source: 'customer',
	source_instance_id: 'customer-instance',
	target: 'cashier',
	revision: 0,
	workflow_sequence: 1,
	payload: {},
};
assert.strictEqual(protocol.validateEnvelope(envelope, sessionId), true);
assert.strictEqual(protocol.validateEnvelope({ ...envelope, version: 999 }, sessionId), false);
assert.strictEqual(protocol.validateEnvelope({ ...envelope, type: 'unknown.event' }, sessionId), false);
assert.strictEqual(protocol.validateEnvelope({ ...envelope, pos_session_id: 'fedcba0987654321' }, sessionId), false);
assert.ok(!protocol.safeQrUrl('http://vietqr.app/img?amount=1'));
assert.ok(!protocol.safeQrUrl('https://evil.example/img?amount=1'));
assert.ok(!protocol.safeQrUrl('https://vietqr.app/not-img?amount=1'));
assert.strictEqual(protocol.safeQrUrl('https://vietqr.app/img?amount=1'), 'https://vietqr.app/img?amount=1');

const received = [];
const transport = createChannel({ source: 'customer', target: 'cashier', onMessage: (message) => received.push(message) });
transport.bind(sessionId);
assert.strictEqual(channels[0].name, 'coffeepos:' + sessionId);
transport.bind('fedcba0987654321');
assert.strictEqual(channels[0].closed, true, 'Rebinding must close the previous channel.');
assert.strictEqual(channels[1].name, 'coffeepos:fedcba0987654321');
transport.close();
assert.strictEqual(channels[1].closed, true);

console.log('Phase 06 protocol scenarios passed.');
