(function (window, document) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    CoffeePOS.core = CoffeePOS.core || {};
    CoffeePOS.core.createPollingController = function (options) {
        const settings = options || {};
        let interval = Math.max(3000, Number(settings.interval) || 5000);
        let timer = 0;
        let controller = null;
        let sequence = 0;
        let destroyed = false;

        function schedule() {
            window.clearTimeout(timer);
            if (!destroyed && !document.hidden) { timer = window.setTimeout(run, interval); }
        }
        async function run() {
            if (destroyed || document.hidden) { return; }
            const current = ++sequence;
            if (controller) { controller.abort(); }
            controller = new window.AbortController();
            if (settings.onStart) { settings.onStart(); }
            try {
                const data = await settings.load(controller.signal);
                if (destroyed || current !== sequence) { return; }
                if (data && Number(data.poll_interval_ms)) { interval = Math.max(3000, Math.min(60000, Number(data.poll_interval_ms))); }
                if (settings.onData) { settings.onData(data); }
            } catch (error) {
                if (!destroyed && current === sequence && (!error || error.name !== 'AbortError') && settings.onError) { settings.onError(error); }
            } finally {
                if (current === sequence) { controller = null; schedule(); }
            }
        }
        function refresh() {
            window.clearTimeout(timer);
            if (controller) { controller.abort(); controller = null; }
            run();
        }
        function onVisibility() {
            window.clearTimeout(timer);
            if (document.hidden) { if (controller) { controller.abort(); controller = null; } return; }
            refresh();
        }
        document.addEventListener('visibilitychange', onVisibility);
        return {
            start: refresh,
            refresh: refresh,
            destroy: function () { destroyed = true; sequence++; window.clearTimeout(timer); if (controller) { controller.abort(); } document.removeEventListener('visibilitychange', onVisibility); },
            isPending: function () { return controller !== null; }
        };
    };
    window.CoffeePOS = CoffeePOS;
}(window, document));
