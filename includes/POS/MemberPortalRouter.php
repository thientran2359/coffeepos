<?php

declare(strict_types=1);

namespace CoffeePOS\POS;

use CoffeePOS\Infrastructure\Settings\Settings;
use CoffeePOS\Infrastructure\Templates\TemplateLoader;
use CoffeePOS\REST\RouteRegistrar;

final class MemberPortalRouter
{
    private TemplateLoader $templates;

    public function __construct(?TemplateLoader $templates = null)
    {
        $this->templates = $templates ?? new TemplateLoader();
    }

    public function register(): void
    {
        add_filter('template_include', [$this, 'filterTemplate'], 20);
        add_filter('show_admin_bar', [$this, 'filterAdminBar']);
        add_action('send_headers', [$this, 'sendPrivateHeaders']);
    }

    public function filterTemplate(string $template): string
    {
        if (! self::isPortalRequest()) {
            return $template;
        }

        $resolved = $this->templates->prepare('member-account', [
            'screen' => 'member-account',
            'route' => self::routeUrl(),
            'rest_namespace' => RouteRegistrar::NAMESPACE,
        ]);

        return $resolved ?? $template;
    }

    public function filterAdminBar(bool $show): bool
    {
        return self::isPortalRequest() ? false : $show;
    }

    public function sendPrivateHeaders(): void
    {
        if (! self::isPortalRequest()) {
            return;
        }

        nocache_headers();
        header('Cache-Control: no-store, private');
    }

    public static function isPortalRequest(): bool
    {
        $pageId = (int) Settings::get(Settings::OPTION_MEMBER_ACCOUNT_PAGE_ID);
        if ($pageId <= 0 || is_admin() || (function_exists('wp_doing_ajax') && wp_doing_ajax())) {
            return false;
        }
        if (function_exists('is_feed') && is_feed()) {
            return false;
        }
        if (function_exists('is_preview') && is_preview()) {
            return false;
        }

        return Settings::isValidMemberAccountPageId($pageId)
            && function_exists('is_page')
            && is_page($pageId)
            && (int) get_queried_object_id() === $pageId;
    }

    public static function routeUrl(): string
    {
        $pageId = (int) Settings::get(Settings::OPTION_MEMBER_ACCOUNT_PAGE_ID);
        $url = $pageId > 0 && function_exists('get_permalink') ? get_permalink($pageId) : false;

        return is_string($url) ? $url : '';
    }
}
