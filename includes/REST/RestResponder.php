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
                $throwable->getMessage(),
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
            Phase01ErrorCodes::PAYMENT_PENDING,
            Phase01ErrorCodes::INVALID_ORDER_STATE,
        ], true)) {
            return 409;
        }

        if (in_array($code, [
            Phase01ErrorCodes::INVALID_PRODUCT,
            Phase01ErrorCodes::VARIATION_NOT_FOUND,
            Phase01ErrorCodes::CUSTOMER_NOT_FOUND,
            Phase01ErrorCodes::ORDER_NOT_FOUND,
        ], true)) {
            return 404;
        }

        return 422;
    }
}
