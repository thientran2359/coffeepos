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

    public const REWRITE_SCHEMA_VERSION = '2';

    private const SCREENS = [
        'entry',
        'cashier',
        'customer',
        'kds',
        'order-queue',
        'order-history',
        'shifts',
        'reports',
        'settings',
    ];

    private TemplateLoader $templateLoader;

    private SettingsScreen $settingsScreen;

    public function __construct(?TemplateLoader $templateLoader = null, ?SettingsScreen $settingsScreen = null)
    {
        $this->templateLoader = $templateLoader ?? new TemplateLoader();
        $this->settingsScreen = $settingsScreen ?? new SettingsScreen();
    }

    public function register(): void
    {
        add_action('init', [self::class, 'registerRewriteRules'], 20);
        add_action('init', [self::class, 'maybeFlushRewriteRules'], 21);
        add_filter('query_vars', [$this, 'addQueryVars']);
        add_filter('template_include', [$this, 'filterTemplate']);
        add_filter('show_admin_bar', [$this, 'filterAdminBar']);
    }

    public function filterAdminBar(bool $show): bool
    {
        return self::isPosRequest() ? false : $show;
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
            'index.php?' . self::QUERY_VAR_SCREEN . '=entry',
            'top'
        );
    }

    public static function maybeFlushRewriteRules(): void
    {
        if ((string) get_option('coffeepos_rewrite_version', '') === self::rewriteVersion()) {
            return;
        }

        flush_rewrite_rules(false);
        update_option('coffeepos_rewrite_version', self::rewriteVersion());
    }

    public static function rewriteVersion(): string
    {
        return COFFEEPOS_VERSION . ':' . self::REWRITE_SCHEMA_VERSION;
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

        if ($screen === 'entry') {
            return $this->entryTemplate($template);
        }

        if ($screen !== 'customer' && ! is_user_logged_in()) {
            $this->redirectToEntry(self::routeUrl($screen));
        }

        $canAccess = $screen === 'customer' || Capabilities::currentUserCanAccessScreen($screen);

        if (! $canAccess) {
            wp_die(
                esc_html__('You are not allowed to access CoffeePOS.', 'coffeepos'),
                esc_html__('Forbidden', 'coffeepos'),
                ['response' => 403]
            );
        }

        $settingsNotice = $screen === 'settings' ? $this->settingsScreen->handleRequest() : [];
        $resolvedTemplate = $this->templateLoader->prepare($screen, [
            'screen' => $screen,
            'route' => self::routeUrl($screen),
            'rest_namespace' => RouteRegistrar::NAMESPACE,
            'navigation' => self::navigationItems(),
            'settings_notice' => $settingsNotice,
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

    public static function routeUrl(string $screen = ''): string
    {
        $baseSlug = trim(Settings::getPosBaseSlug(), '/');

        if ($baseSlug === '') {
            $baseSlug = 'pos';
        }

        return home_url('/' . $baseSlug . ($screen === '' || $screen === 'entry' ? '/' : '/' . $screen . '/'));
    }

    public static function navigationItems(): array
    {
        $labels = [
            'cashier' => __('Cashier', 'coffeepos'),
            'kds' => __('Kitchen Display', 'coffeepos'),
            'order-queue' => __('Order Queue', 'coffeepos'),
            'shifts' => __('Shifts', 'coffeepos'),
            'order-history' => __('Order History', 'coffeepos'),
            'reports' => __('Reports', 'coffeepos'),
        ];
        $items = [];

        foreach ($labels as $screen => $label) {
            if (Capabilities::currentUserCanAccessScreen($screen)) {
                $items[] = ['screen' => $screen, 'label' => $label, 'url' => self::routeUrl($screen)];
            }
        }

        if (current_user_can(Capabilities::MANAGE_SETTINGS)) {
            $items[] = [
                'screen' => 'settings',
                'label' => __('Settings', 'coffeepos'),
                'url' => self::routeUrl('settings'),
            ];
        }

        return $items;
    }

    private function entryTemplate(string $fallback): string
    {
        $error = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $nonce = isset($_POST['coffeepos_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['coffeepos_nonce'])) : '';

            if (! wp_verify_nonce($nonce, 'coffeepos_staff_login')) {
                $error = __('Your login form expired. Please try again.', 'coffeepos');
            } else {
                $username = isset($_POST['log']) ? sanitize_text_field(wp_unslash((string) $_POST['log'])) : '';
                $password = isset($_POST['pwd']) ? (string) wp_unslash($_POST['pwd']) : '';
                $user = wp_signon([
                    'user_login' => $username,
                    'user_password' => $password,
                    'remember' => ! empty($_POST['rememberme']),
                ], is_ssl());

                if (is_wp_error($user)) {
                    $error = __('The username/email or password is incorrect.', 'coffeepos');
                } else {
                    wp_set_current_user((int) $user->ID);
                    $target = $this->safeReturnTarget((string) ($_POST['redirect_to'] ?? ''));
                    $landing = $target !== '' ? $target : $this->firstPermittedRoute();
                    if ($landing !== '') {
                        wp_safe_redirect($landing);
                        exit;
                    }
                }
            }
        }

        if (is_user_logged_in() && $error === '') {
            $landing = $this->firstPermittedRoute();
            if ($landing !== '') {
                wp_safe_redirect($landing);
                exit;
            }
        }

        $resolved = $this->templateLoader->prepare('login', [
            'screen' => 'login',
            'route' => self::routeUrl(),
            'error' => $error,
            'no_access' => is_user_logged_in() && ! Capabilities::currentUserCanAccessPos(),
            'redirect_to' => $this->safeReturnTarget((string) ($_REQUEST['redirect_to'] ?? ''), false),
        ]);

        return $resolved ?? $fallback;
    }

    private function firstPermittedRoute(): string
    {
        foreach (['cashier', 'kds', 'order-queue', 'shifts', 'order-history', 'reports', 'settings'] as $screen) {
            if (Capabilities::currentUserCanAccessScreen($screen)) {
                return self::routeUrl($screen);
            }
        }

        return '';
    }

    private function redirectToEntry(string $target): void
    {
        wp_safe_redirect(add_query_arg('redirect_to', $target, self::routeUrl()));
        exit;
    }

    private function safeReturnTarget(string $target, bool $requireCapability = true): string
    {
        $target = rawurldecode(wp_unslash($target));

        foreach (Capabilities::screenCapabilities() as $screen => $capability) {
            $route = self::routeUrl($screen);
            if (untrailingslashit($target) === untrailingslashit($route) && (! $requireCapability || current_user_can($capability))) {
                return $route;
            }
        }

        return '';
    }
}
