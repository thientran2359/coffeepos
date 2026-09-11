(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    CoffeePOS.state = CoffeePOS.state || {};

    function compare(left, right) {
        const leftTime = String(left && left.received_at || '');
        const rightTime = String(right && right.received_at || '');
        if (leftTime !== rightTime) {
            if (leftTime === '') { return 1; }
            if (rightTime === '') { return -1; }
            return leftTime < rightTime ? -1 : 1;
        }
        return Number(left && left.id || 0) - Number(right && right.id || 0);
    }

    CoffeePOS.state.sortOperationalOrders = function (orders) {
        return (Array.isArray(orders) ? orders.slice() : []).sort(compare);
    };
    window.CoffeePOS = CoffeePOS;
}(window));
