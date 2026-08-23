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

    public const INVALID_CUSTOMER_PHONE = 'invalid_customer_phone';

    public const CUSTOMER_LOOKUP_FAILED = 'customer_lookup_failed';

    public const CUSTOMER_PHONE_AMBIGUOUS = 'customer_phone_ambiguous';

    public const INVALID_CUSTOMER = 'invalid_customer';

    public const INVALID_TABLE = 'invalid_table';

    public const INVALID_COUPON = 'invalid_coupon';

    public const INVALID_CONFIGURATION = 'invalid_configuration';

    public const INVALID_CART = 'invalid_cart';

    public const CART_SESSION_NOT_FOUND = 'cart_session_not_found';

    public const CART_REVISION_CONFLICT = 'cart_revision_conflict';

    public const EMPTY_CART = 'empty_cart';
    public const COUPON_NOT_APPLICABLE = 'coupon_not_applicable';
    public const INVALID_PAYMENT = 'invalid_payment';
    public const INSUFFICIENT_CASH = 'insufficient_cash';
    public const ORDER_CREATION_FAILED = 'order_creation_failed';
    public const PAYMENT_FAILED = 'payment_failed';
    public const PAYMENT_PENDING = 'payment_pending';
    public const PAYMENT_PROVIDER_UNAVAILABLE = 'payment_provider_unavailable';
    public const PAYMENT_VERIFICATION_FAILED = 'payment_verification_failed';
    public const INVALID_ORDER_STATE = 'invalid_order_state';
    public const ORDER_NOT_FOUND = 'order_not_found';
    public const DUPLICATE_OPERATION_CONFLICT = 'duplicate_operation_conflict';
}
