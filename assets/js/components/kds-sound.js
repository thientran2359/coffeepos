(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    CoffeePOS.components = CoffeePOS.components || {};
    CoffeePOS.components.createKdsSound = function (available) {
        const key = 'coffeepos:kds:sound-enabled';
        let enabled = false;
        let context = null;
        let initialized = false;
        const seen = new Set();
        try { enabled = available !== false && window.localStorage.getItem(key) === '1'; } catch (error) {}
        function beep() {
            if (!enabled) { return; }
            try {
                context = context || new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = context.createOscillator(); const gain = context.createGain();
                oscillator.frequency.value = 880; gain.gain.value = 0.08; oscillator.connect(gain); gain.connect(context.destination); oscillator.start(); oscillator.stop(context.currentTime + 0.18);
            } catch (error) { enabled = false; }
        }
        return {
            toggle: function () { if (available === false) { return false; } enabled = !enabled; try { window.localStorage.setItem(key, enabled ? '1' : '0'); } catch (error) {} if (enabled) { beep(); } return enabled; },
            isEnabled: function () { return enabled; },
            accept: function (orders) { const fresh = (orders || []).filter(function (order) { return order.kds && order.kds.state === 'new' && !seen.has(Number(order.id)); }); (orders || []).forEach(function (order) { seen.add(Number(order.id)); }); if (available !== false && initialized && fresh.length) { beep(); } initialized = true; }
        };
    };
    window.CoffeePOS = CoffeePOS;
}(window));
