<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Error;

final class Phase01ErrorCodes
{
    public const INVALID_PRODUCT = 'invalid_product';

    public const INVALID_VARIATION = 'invalid_variation';

    public const VARIATION_NOT_FOUND = 'variation_not_found';

    public const INVALID_QUANTITY = 'invalid_quantity';

    public const OUT_OF_STOCK = 'out_of_stock';

    public const INVALID_ORDER_TYPE = 'invalid_order_type';

    public const TABLE_REQUIRED = 'table_required';

    public const TABLE_NOT_ALLOWED = 'table_not_allowed';

    public const CUSTOMER_NOT_FOUND = 'customer_not_found';

    public const INVALID_CUSTOMER = 'invalid_customer';

    public const INVALID_COUPON = 'invalid_coupon';

    public const INVALID_CONFIGURATION = 'invalid_configuration';

    public const INVALID_CART = 'invalid_cart';

    public const CART_SESSION_NOT_FOUND = 'cart_session_not_found';

    public const CART_REVISION_CONFLICT = 'cart_revision_conflict';
}
