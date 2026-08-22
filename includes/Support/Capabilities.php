<?php

declare(strict_types=1);

namespace CoffeePOS\Support;

final class Capabilities
{
    public const MANAGE_WOOCOMMERCE = 'manage_woocommerce';

    public const VIEW_ADMIN_DASHBOARD = 'view_admin_dashboard';

    public static function register(): void
    {
        foreach (['administrator', 'shop_manager'] as $roleName) {
            $role = get_role($roleName);

            if ($role === null) {
                continue;
            }

            $role->add_cap(self::VIEW_ADMIN_DASHBOARD);
        }
    }

    public static function currentUserCanAccessPos(): bool
    {
        return current_user_can(self::MANAGE_WOOCOMMERCE) || current_user_can(self::VIEW_ADMIN_DASHBOARD);
    }
}
