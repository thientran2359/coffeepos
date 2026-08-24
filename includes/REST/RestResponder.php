<?php

declare(strict_types=1);

namespace CoffeePOS\REST;

use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Infrastructure\Logging\Logger;
use WP_REST_Response;

final class RestResponder
{
    public static function success(array $data, int $status = 200): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => true,
            'data' => $data,
        ], $status);
    }

    public static function error(string $code, string $message, int $status, array $details = []): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status);
    }

    public static function fromThrowable(\Throwable $throwable): WP_REST_Response
    {
        if ($throwable instanceof Phase01Exception) {
            return self::error(
                $throwable->errorCode(),
                self::publicMessage($throwable->errorCode()),
                self::statusForCode($throwable->errorCode()),
                $throwable->context()
            );
        }

        Logger::error('Unexpected POS REST error.', [
            'exception' => get_class($throwable),
            'message' => $throwable->getMessage(),
        ]);

        return self::error(
            'coffeepos_rest_error',
            __('Unexpected error while processing the POS request.', 'coffeepos'),
            500
        );
    }

    private static function statusForCode(string $code): int
    {
        if ($code === Phase01ErrorCodes::CART_SESSION_NOT_FOUND) {
            return 404;
        }

        if ($code === Phase01ErrorCodes::CART_REVISION_CONFLICT) {
            return 409;
        }

        if (in_array($code, [
            Phase01ErrorCodes::DUPLICATE_OPERATION_CONFLICT,
            Phase01ErrorCodes::IDEMPOTENCY_KEY_REUSED,
            Phase01ErrorCodes::CUSTOMER_PHONE_EXISTS,
            Phase01ErrorCodes::CUSTOMER_PHONE_AMBIGUOUS,
            Phase01ErrorCodes::CUSTOMER_CREATION_LOCKED,
            Phase01ErrorCodes::PAYMENT_PENDING,
            Phase01ErrorCodes::INVALID_ORDER_STATE,
            Phase01ErrorCodes::ORDER_STATE_CONFLICT,
            Phase01ErrorCodes::SHIFT_ALREADY_OPEN,
            Phase01ErrorCodes::SHIFT_STATE_CONFLICT,
        ], true)) {
            return 409;
        }

        if ($code === Phase01ErrorCodes::CUSTOMER_LOOKUP_FAILED) {
            return 503;
        }

        if ($code === Phase01ErrorCodes::CUSTOMER_CREATE_FAILED) {
            return 500;
        }

        if ($code === Phase01ErrorCodes::SHIFT_WRITE_FAILED) {
            return 500;
        }

        if (in_array($code, [
            Phase01ErrorCodes::REFUND_FAILED,
            Phase01ErrorCodes::REORDER_FAILED,
            Phase01ErrorCodes::REPORT_QUERY_FAILED,
            Phase01ErrorCodes::REPORT_EXPORT_FAILED,
        ], true)) {
            return 500;
        }

        if ($code === Phase01ErrorCodes::REPORT_EXPORT_UNAVAILABLE) {
            return 503;
        }

        if (in_array($code, [
            Phase01ErrorCodes::INVALID_CUSTOMER_PHONE,
            Phase01ErrorCodes::INVALID_CUSTOMER_NAME,
            Phase01ErrorCodes::INVALID_CUSTOMER_EMAIL,
            Phase01ErrorCodes::INVALID_SHIFT_AMOUNT,
            Phase01ErrorCodes::INVALID_SHIFT_NOTE,
            Phase01ErrorCodes::INVALID_ORDER_FILTER,
            Phase01ErrorCodes::INVALID_REFUND,
            Phase01ErrorCodes::INVALID_REPORT_RANGE,
            Phase01ErrorCodes::REPORT_RANGE_TOO_LARGE,
        ], true)) {
            return 400;
        }

        if (in_array($code, [
            Phase01ErrorCodes::INVALID_PRODUCT,
            Phase01ErrorCodes::VARIATION_NOT_FOUND,
            Phase01ErrorCodes::CUSTOMER_NOT_FOUND,
            Phase01ErrorCodes::ORDER_NOT_FOUND,
            Phase01ErrorCodes::SHIFT_NOT_FOUND,
        ], true)) {
            return 404;
        }

        return 422;
    }

    private static function publicMessage(string $code): string
    {
        $messages = [
            Phase01ErrorCodes::INVALID_PRODUCT => __('The product is invalid or unavailable.', 'coffeepos'),
            Phase01ErrorCodes::INVALID_VARIATION => __('The selected variation is invalid.', 'coffeepos'),
            Phase01ErrorCodes::VARIATION_NOT_FOUND => __('The selected variation was not found.', 'coffeepos'),
            Phase01ErrorCodes::INVALID_QUANTITY => __('The selected quantity is invalid.', 'coffeepos'),
            Phase01ErrorCodes::OUT_OF_STOCK => __('The requested item is out of stock.', 'coffeepos'),
            Phase01ErrorCodes::INVALID_ORDER_TYPE => __('The selected service type is invalid.', 'coffeepos'),
            Phase01ErrorCodes::TABLE_REQUIRED => __('Select a table for dine-in service.', 'coffeepos'),
            Phase01ErrorCodes::TABLE_NOT_ALLOWED => __('A table cannot be used with this service type.', 'coffeepos'),
            Phase01ErrorCodes::CUSTOMER_NOT_FOUND => __('The customer was not found.', 'coffeepos'),
            Phase01ErrorCodes::INVALID_CUSTOMER_PHONE => __('Enter a valid customer phone number.', 'coffeepos'),
            Phase01ErrorCodes::CUSTOMER_LOOKUP_FAILED => __('The customer lookup is temporarily unavailable.', 'coffeepos'),
            Phase01ErrorCodes::CUSTOMER_PHONE_AMBIGUOUS => __('More than one customer uses this phone number.', 'coffeepos'),
            Phase01ErrorCodes::CUSTOMER_PHONE_EXISTS => __('A customer already uses this phone number.', 'coffeepos'),
            Phase01ErrorCodes::INVALID_CUSTOMER_NAME => __('Enter a valid customer name.', 'coffeepos'),
            Phase01ErrorCodes::INVALID_CUSTOMER_EMAIL => __('Enter a valid customer email address.', 'coffeepos'),
            Phase01ErrorCodes::CUSTOMER_CREATE_FAILED => __('The customer could not be created.', 'coffeepos'),
            Phase01ErrorCodes::CUSTOMER_CREATION_LOCKED => __('Customer creation is already in progress.', 'coffeepos'),
            Phase01ErrorCodes::IDEMPOTENCY_KEY_REUSED => __('This customer request was already used with different information.', 'coffeepos'),
            Phase01ErrorCodes::INVALID_CUSTOMER => __('The selected customer is invalid.', 'coffeepos'),
            Phase01ErrorCodes::INVALID_TABLE => __('The selected table is invalid.', 'coffeepos'),
            Phase01ErrorCodes::INVALID_COUPON => __('The coupon is invalid.', 'coffeepos'),
            Phase01ErrorCodes::INVALID_CONFIGURATION => __('CoffeePOS configuration is invalid or incomplete.', 'coffeepos'),
            Phase01ErrorCodes::INVALID_CART => __('The cart is not available for this action.', 'coffeepos'),
            Phase01ErrorCodes::INVALID_ORDER_NOTE => __('The order note is invalid or too long.', 'coffeepos'),
            Phase01ErrorCodes::CART_SESSION_NOT_FOUND => __('The cart session was not found.', 'coffeepos'),
            Phase01ErrorCodes::CART_REVISION_CONFLICT => __('The cart changed on another request. Review the latest cart and try again.', 'coffeepos'),
            Phase01ErrorCodes::EMPTY_CART => __('The cart is empty.', 'coffeepos'),
            Phase01ErrorCodes::COUPON_NOT_APPLICABLE => __('The coupon cannot be applied to this cart.', 'coffeepos'),
            Phase01ErrorCodes::INVALID_PAYMENT => __('The payment information is invalid.', 'coffeepos'),
            Phase01ErrorCodes::INSUFFICIENT_CASH => __('Cash received is below the authoritative order total.', 'coffeepos'),
            Phase01ErrorCodes::ORDER_CREATION_FAILED => __('The WooCommerce order could not be created.', 'coffeepos'),
            Phase01ErrorCodes::PAYMENT_FAILED => __('The payment could not be completed.', 'coffeepos'),
            Phase01ErrorCodes::PAYMENT_PENDING => __('Checkout is already being processed.', 'coffeepos'),
            Phase01ErrorCodes::PAYMENT_PROVIDER_UNAVAILABLE => __('The payment provider is unavailable.', 'coffeepos'),
            Phase01ErrorCodes::PAYMENT_VERIFICATION_FAILED => __('The payment could not be verified.', 'coffeepos'),
            Phase01ErrorCodes::INVALID_ORDER_STATE => __('The order cannot perform this transition.', 'coffeepos'),
            Phase01ErrorCodes::ORDER_NOT_FOUND => __('The order was not found.', 'coffeepos'),
            Phase01ErrorCodes::DUPLICATE_OPERATION_CONFLICT => __('This operation was already used with different information.', 'coffeepos'),
            Phase01ErrorCodes::ORDER_STATE_CONFLICT => __('The order changed on another screen. Refresh and try again.', 'coffeepos'),
            Phase01ErrorCodes::SHIFT_REQUIRED => __('Open a shift before completing checkout.', 'coffeepos'),
            Phase01ErrorCodes::SHIFT_ALREADY_OPEN => __('This cashier already has an open shift.', 'coffeepos'),
            Phase01ErrorCodes::SHIFT_NOT_FOUND => __('The shift was not found.', 'coffeepos'),
            Phase01ErrorCodes::SHIFT_STATE_CONFLICT => __('The shift changed on another request.', 'coffeepos'),
            Phase01ErrorCodes::SHIFT_WRITE_FAILED => __('The shift could not be saved.', 'coffeepos'),
            Phase01ErrorCodes::INVALID_SHIFT_AMOUNT => __('Enter a valid shift amount.', 'coffeepos'),
            Phase01ErrorCodes::INVALID_SHIFT_NOTE => __('The shift note is invalid or too long.', 'coffeepos'),
            Phase01ErrorCodes::INVALID_ORDER_FILTER => __('One or more order filters are invalid.', 'coffeepos'),
            Phase01ErrorCodes::INVALID_REFUND => __('The refund information is invalid.', 'coffeepos'),
            Phase01ErrorCodes::REFUND_FAILED => __('The refund could not be completed.', 'coffeepos'),
            Phase01ErrorCodes::REORDER_FAILED => __('The order could not be reordered.', 'coffeepos'),
            Phase01ErrorCodes::INVALID_REPORT_RANGE => __('The report date range is invalid.', 'coffeepos'),
            Phase01ErrorCodes::REPORT_RANGE_TOO_LARGE => __('The report date range is too large.', 'coffeepos'),
            Phase01ErrorCodes::REPORT_QUERY_FAILED => __('The report could not be loaded.', 'coffeepos'),
            Phase01ErrorCodes::REPORT_EXPORT_FAILED => __('The report export could not be created.', 'coffeepos'),
            Phase01ErrorCodes::REPORT_EXPORT_UNAVAILABLE => __('This report export format is unavailable on the server.', 'coffeepos'),
        ];

        return $messages[$code] ?? __('The POS request could not be completed.', 'coffeepos');
    }
}
