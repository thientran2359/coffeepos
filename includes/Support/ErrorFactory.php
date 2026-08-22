<?php

declare(strict_types=1);

namespace CoffeePOS\Support;

use WP_Error;

final class ErrorFactory
{
    public static function forbidden(string $code, string $message, array $details = []): WP_Error
    {
        return new WP_Error($code, $message, [
            'status' => rest_authorization_required_code(),
            'details' => $details,
        ]);
    }

    public static function restError(string $code, string $message, int $status, array $details = []): WP_Error
    {
        return new WP_Error($code, $message, [
            'status' => $status,
            'details' => $details,
        ]);
    }
}
