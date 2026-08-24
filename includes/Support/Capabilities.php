<?php

declare(strict_types=1);

namespace CoffeePOS\Support;

final class Capabilities
{
    public const MANAGE_WOOCOMMERCE = 'manage_woocommerce';

    public const VIEW_ADMIN_DASHBOARD = 'view_admin_dashboard';

    public const ACCESS_CASHIER = 'coffeepos_access_cashier';
    public const ACCESS_KDS = 'coffeepos_access_kds';
    public const ACCESS_ORDER_QUEUE = 'coffeepos_access_order_queue';
    public const MANAGE_OWN_SHIFT = 'coffeepos_manage_own_shift';
    public const VIEW_ORDER_HISTORY = 'coffeepos_view_order_history';
    public const REPRINT_RECEIPTS = 'coffeepos_reprint_receipts';
    public const REORDER_ORDERS = 'coffeepos_reorder_orders';
    public const CANCEL_ORDERS = 'coffeepos_cancel_orders';
    public const REFUND_ORDERS = 'coffeepos_refund_orders';
    public const VIEW_REPORTS = 'coffeepos_view_reports';
    public const MANAGE_SETTINGS = 'coffeepos_manage_settings';

    private const ROLE_CAPABILITIES = [
        'coffeepos_cashier' => [
            self::ACCESS_CASHIER,
            self::MANAGE_OWN_SHIFT,
            self::VIEW_ORDER_HISTORY,
            self::REPRINT_RECEIPTS,
            self::REORDER_ORDERS,
        ],
        'coffeepos_kitchen' => [
            self::ACCESS_KDS,
            self::ACCESS_ORDER_QUEUE,
        ],
        'coffeepos_supervisor' => [
            self::ACCESS_CASHIER,
            self::ACCESS_KDS,
            self::ACCESS_ORDER_QUEUE,
            self::MANAGE_OWN_SHIFT,
            self::VIEW_ORDER_HISTORY,
            self::REPRINT_RECEIPTS,
            self::REORDER_ORDERS,
            self::CANCEL_ORDERS,
        ],
        'coffeepos_manager' => [
            self::ACCESS_CASHIER,
            self::ACCESS_KDS,
            self::ACCESS_ORDER_QUEUE,
            self::MANAGE_OWN_SHIFT,
            self::VIEW_ORDER_HISTORY,
            self::REPRINT_RECEIPTS,
            self::REORDER_ORDERS,
            self::CANCEL_ORDERS,
            self::REFUND_ORDERS,
            self::VIEW_REPORTS,
            self::MANAGE_SETTINGS,
        ],
    ];

    private const ROLE_LABELS = [
        'coffeepos_cashier' => 'CoffeePOS Cashier',
        'coffeepos_kitchen' => 'CoffeePOS Kitchen',
        'coffeepos_supervisor' => 'CoffeePOS Supervisor',
        'coffeepos_manager' => 'CoffeePOS Manager',
    ];

    private const SCREEN_CAPABILITIES = [
        'cashier' => self::ACCESS_CASHIER,
        'kds' => self::ACCESS_KDS,
        'order-queue' => self::ACCESS_ORDER_QUEUE,
        'shifts' => self::MANAGE_OWN_SHIFT,
        'order-history' => self::VIEW_ORDER_HISTORY,
        'reports' => self::VIEW_REPORTS,
        'settings' => self::MANAGE_SETTINGS,
    ];

    public static function register(): void
    {
        foreach (self::ROLE_CAPABILITIES as $roleName => $capabilities) {
            $role = get_role($roleName);

            if ($role === null) {
                $role = add_role($roleName, __(self::ROLE_LABELS[$roleName], 'coffeepos'), ['read' => true]);
            }

            if ($role === null) {
                continue;
            }

            $capabilitiesForRole = array_fill_keys($capabilities, true);
            foreach (self::all() as $capability) {
                if (isset($capabilitiesForRole[$capability])) {
                    $role->add_cap($capability);
                } else {
                    $role->remove_cap($capability);
                }
            }
        }

        foreach (['administrator', 'shop_manager'] as $roleName) {
            $role = get_role($roleName);

            if ($role === null) {
                continue;
            }

            foreach (self::all() as $capability) {
                $role->add_cap($capability);
            }
        }
    }

    public static function currentUserCanAccessPos(): bool
    {
        foreach (self::SCREEN_CAPABILITIES as $capability) {
            if (current_user_can($capability)) {
                return true;
            }
        }

        return false;
    }

    public static function all(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::ROLE_CAPABILITIES))));
    }

    public static function forScreen(string $screen): ?string
    {
        return self::SCREEN_CAPABILITIES[$screen] ?? null;
    }

    public static function currentUserCanAccessScreen(string $screen): bool
    {
        $capability = self::forScreen($screen);

        return $capability !== null && current_user_can($capability);
    }

    public static function screenCapabilities(): array
    {
        return self::SCREEN_CAPABILITIES;
    }
}
