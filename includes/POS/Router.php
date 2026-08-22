<?php

declare(strict_types=1);

namespace CoffeePOS\POS;

use CoffeePOS\Infrastructure\Settings\Settings;
use CoffeePOS\Infrastructure\Templates\TemplateLoader;
use CoffeePOS\REST\RouteRegistrar;
use CoffeePOS\Support\Capabilities;

final class Router
{
    public const QUERY_VAR_SCREEN = 'coffeepos_screen';

    private const SCREENS = [
        'cashier',
        'customer',
        'kds',
        'order-queue',
        'order-history',
        'shifts',
        'reports',
    ];

    private TemplateLoader $templateLoader;

    public function __construct(?TemplateLoader $templateLoader = null)
    {
        $this->templateLoader = $templateLoader ?? new TemplateLoader();
    }

    public function register(): void
    {
        add_action('init', [self::class, 'registerRewriteRules'], 20);
        add_filter('query_vars', [$this, 'addQueryVars']);
        add_filter('template_include', [$this, 'filterTemplate']);
    }

    public static function registerRewriteRules(?string $baseSlug = null): void
    {
        $normalizedBase = trim($baseSlug ?? Settings::getPosBaseSlug(), '/');

        if ($normalizedBase === '') {
            $normalizedBase = 'pos';
        }

        $screensPattern = implode('|', array_map(static function (string $screen): string {
            return preg_quote($screen, '/');
        }, self::SCREENS));

        add_rewrite_tag('%' . self::QUERY_VAR_SCREEN . '%', '([^&]+)');
        add_rewrite_rule(
            '^' . preg_quote($normalizedBase, '/') . '/(' . $screensPattern . ')/?$',
            'index.php?' . self::QUERY_VAR_SCREEN . '=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^' . preg_quote($normalizedBase, '/') . '/?$',
            'index.php?' . self::QUERY_VAR_SCREEN . '=cashier',
            'top'
        );
    }

    public function addQueryVars(array $vars): array
    {
        $vars[] = self::QUERY_VAR_SCREEN;

        return $vars;
    }

    public function filterTemplate(string $template): string
    {
        $screen = self::currentScreen();

        if ($screen === null) {
            return $template;
        }

        if (! Capabilities::currentUserCanAccessPos()) {
            wp_die(
                esc_html__('You are not allowed to access CoffeePOS.', 'coffeepos'),
                esc_html__('Forbidden', 'coffeepos'),
                ['response' => 403]
            );
        }

        $resolvedTemplate = $this->templateLoader->prepare($screen, [
            'screen' => $screen,
            'route' => self::routeUrl($screen),
            'rest_namespace' => RouteRegistrar::NAMESPACE,
        ]);

        if ($resolvedTemplate === null) {
            return $template;
        }

        return $resolvedTemplate;
    }

    public static function isPosRequest(): bool
    {
        return self::currentScreen() !== null;
    }

    public static function currentScreen(): ?string
    {
        $screen = get_query_var(self::QUERY_VAR_SCREEN);

        if (! is_string($screen) || $screen === '') {
            return null;
        }

        if (! in_array($screen, self::SCREENS, true)) {
            return null;
        }

        return $screen;
    }

    public static function screens(): array
    {
        return self::SCREENS;
    }

    private static function routeUrl(string $screen): string
    {
        $baseSlug = trim(Settings::getPosBaseSlug(), '/');

        if ($baseSlug === '') {
            $baseSlug = 'pos';
        }

        return home_url('/' . $baseSlug . '/' . $screen . '/');
    }
}
