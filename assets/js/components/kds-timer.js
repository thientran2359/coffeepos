(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    CoffeePOS.components = CoffeePOS.components || {};
    CoffeePOS.components.createKdsTimer = function (root, offsetProvider) {
        let timer = 0;
        function format(seconds) { const hours = Math.floor(seconds / 3600); const minutes = Math.floor((seconds % 3600) / 60); const secs = seconds % 60; return (hours ? String(hours) + ':' : '') + String(minutes).padStart(2, '0') + ':' + String(secs).padStart(2, '0'); }
        function tick() {
            root.querySelectorAll('[data-component="kds-order-card"]').forEach(function (card) {
                const received = Date.parse(card.getAttribute('data-received-at') || '');
                const timerElement = card.querySelector('[data-component="kds-timer"]');
                if (!Number.isFinite(received)) { if (timerElement) { timerElement.textContent = '--:--'; } card.setAttribute('data-severity', 'normal'); return; }
                const seconds = Math.max(0, Math.floor((Date.now() + Number(offsetProvider() || 0) - received) / 1000));
                if (timerElement) { timerElement.textContent = format(seconds); }
                card.setAttribute('data-severity', seconds >= 600 ? 'critical' : (seconds >= 300 ? 'warning' : 'normal'));
            });
        }
        return { start: function () { tick(); window.clearInterval(timer); timer = window.setInterval(tick, 1000); }, tick: tick, destroy: function () { window.clearInterval(timer); } };
    };
    window.CoffeePOS = CoffeePOS;
}(window));
