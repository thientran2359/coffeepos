<?php

declare(strict_types=1);

namespace CoffeePOS\Infrastructure\Logging;

final class Logger
{
    private const SOURCE = 'coffeepos';

    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        $context['source'] = self::SOURCE;

        if (function_exists('wc_get_logger')) {
            wc_get_logger()->log($level, $message, $context);

            return;
        }

        $encodedContext = wp_json_encode($context);

        error_log(sprintf('[CoffeePOS][%s] %s %s', strtoupper($level), $message, (string) $encodedContext));
    }
}
