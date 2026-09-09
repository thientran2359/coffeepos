<?php

declare(strict_types=1);

namespace CoffeePOS\Infrastructure\Concurrency;

use CoffeePOS\Application\Contracts\LockProviderInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;

final class MySqlLockProvider implements LockProviderInterface
{
    /**
     * @return mixed
     */
    public function synchronized(string $key, callable $callback)
    {
        global $wpdb;

        $shiftLock = strpos($key, 'shift-user-') === 0;
        $orderLock = strpos($key, 'refund-order-') === 0 || strpos($key, 'reorder-order-') === 0;
        $heldCartLock = strpos($key, 'held-cart-') === 0;
        $errorCode = $shiftLock || $orderLock ? Phase01ErrorCodes::ORDER_STATE_CONFLICT : Phase01ErrorCodes::CUSTOMER_CREATION_LOCKED;
        if ($shiftLock) { $errorCode = Phase01ErrorCodes::SHIFT_STATE_CONFLICT; }
        if ($heldCartLock) { $errorCode = Phase01ErrorCodes::HELD_CART_STATE_CONFLICT; }
        $unavailableMessage = $shiftLock ? 'Shift update is temporarily unavailable.' : ($orderLock ? 'Order operation is temporarily unavailable.' : ($heldCartLock ? 'Held cart operation is temporarily unavailable.' : 'Member creation is temporarily unavailable.'));
        $busyMessage = $shiftLock ? 'Another shift update is already in progress.' : ($orderLock ? 'Another order operation is already in progress.' : ($heldCartLock ? 'Another held cart operation is already in progress.' : 'Member creation is already in progress. Please retry.'));

        if (! isset($wpdb) || ! is_object($wpdb)) {
            throw Phase01Exception::withCode(
                $errorCode,
                $unavailableMessage
            );
        }

        $lockName = 'coffeepos:' . substr(hash('sha256', $key), 0, 40);
        $locked = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lockName)) === 1;

        if (! $locked) {
            throw Phase01Exception::withCode(
                $errorCode,
                $busyMessage
            );
        }

        try {
            return $callback();
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
        }
    }
}
