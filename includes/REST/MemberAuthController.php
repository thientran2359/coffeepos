<?php

declare(strict_types=1);

namespace CoffeePOS\REST;

use CoffeePOS\Application\MemberPortal\MemberAuthException;
use CoffeePOS\Application\MemberPortal\MemberAuthService;
use CoffeePOS\Application\MemberPortal\MemberSessionService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class MemberAuthController
{
    private MemberAuthService $auth;

    public function __construct(?MemberAuthService $auth = null)
    {
        $this->auth = $auth ?? new MemberAuthService();
    }

    public function register(string $namespace): void
    {
        register_rest_route($namespace, '/member/auth/login', [[
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'login'],
            'permission_callback' => '__return_true',
        ]]);
        register_rest_route($namespace, '/member/auth/session', [[
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'session'],
            'permission_callback' => '__return_true',
        ]]);
        register_rest_route($namespace, '/member/auth/change-pin', [[
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'changePin'],
            'permission_callback' => '__return_true',
        ]]);
        register_rest_route($namespace, '/member/auth/logout', [[
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'logout'],
            'permission_callback' => '__return_true',
        ]]);
    }

    public function login(WP_REST_Request $request): WP_REST_Response
    {
        if (! $this->isSameOrigin($request)) {
            return $this->error(new MemberAuthException('member_csrf_invalid', __('The login request is not allowed.', 'coffeepos'), 403));
        }

        try {
            $payload = $this->payload($request);
            $session = $this->auth->login((string) ($payload['phone'] ?? ''), (string) ($payload['pin'] ?? ''));
            $this->setSessionCookie((string) $session['token'], (int) $session['expires_at']);

            return $this->success($this->publicSession($session));
        } catch (MemberAuthException $error) {
            return $this->error($error);
        } catch (\Throwable $error) {
            return $this->unexpected();
        }
    }

    public function session(WP_REST_Request $request): WP_REST_Response
    {
        try {
            return $this->success($this->publicSession($this->auth->session($this->cookieToken())));
        } catch (MemberAuthException $error) {
            $this->expireSessionCookie();
            return $this->error($error);
        } catch (\Throwable $error) {
            return $this->unexpected();
        }
    }

    public function changePin(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $current = $this->auth->session($this->cookieToken());
            $payload = $this->payload($request);
            $session = $this->auth->changePin(
                $current,
                $this->csrfToken($request),
                (string) ($payload['new_pin'] ?? ''),
                (string) ($payload['new_pin_confirmation'] ?? '')
            );
            $this->setSessionCookie((string) $session['token'], (int) $session['expires_at']);

            return $this->success($this->publicSession($session));
        } catch (MemberAuthException $error) {
            return $this->error($error);
        } catch (\Throwable $error) {
            return $this->unexpected();
        }
    }

    public function logout(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $session = $this->auth->session($this->cookieToken());
            $this->auth->logout($session, $this->csrfToken($request));
            $this->expireSessionCookie();

            return $this->success(['logged_out' => true]);
        } catch (MemberAuthException $error) {
            if ($error->status() === 401) {
                $this->expireSessionCookie();
            }
            return $this->error($error);
        } catch (\Throwable $error) {
            return $this->unexpected();
        }
    }

    private function payload(WP_REST_Request $request): array
    {
        $json = $request->get_json_params();

        return is_array($json) ? $json : [];
    }

    private function publicSession(array $session): array
    {
        return [
            'authenticated' => true,
            'pin_change_required' => ! empty($session['pin_change_required']),
            'csrf_token' => (string) ($session['csrf_token'] ?? ''),
            'expires_at' => (int) ($session['expires_at'] ?? 0),
        ];
    }

    private function cookieToken(): string
    {
        $value = $_COOKIE[MemberSessionService::COOKIE_NAME] ?? '';

        return is_string($value) ? trim(wp_unslash($value)) : '';
    }

    private function csrfToken(WP_REST_Request $request): string
    {
        return sanitize_text_field((string) $request->get_header('X-CoffeePOS-Member-CSRF'));
    }

    private function setSessionCookie(string $token, int $expires): void
    {
        if (headers_sent()) {
            throw new MemberAuthException('member_portal_unavailable', __('Member access is temporarily unavailable.', 'coffeepos'), 503);
        }

        $options = [
            'expires' => $expires,
            'path' => MemberSessionService::cookiePath(),
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if (defined('COOKIE_DOMAIN') && (string) COOKIE_DOMAIN !== '') {
            $options['domain'] = (string) COOKIE_DOMAIN;
        }

        setcookie(MemberSessionService::COOKIE_NAME, $token, $options);
        $_COOKIE[MemberSessionService::COOKIE_NAME] = $token;
    }

    private function expireSessionCookie(): void
    {
        if (headers_sent()) {
            return;
        }

        $options = [
            'expires' => time() - YEAR_IN_SECONDS,
            'path' => MemberSessionService::cookiePath(),
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if (defined('COOKIE_DOMAIN') && (string) COOKIE_DOMAIN !== '') {
            $options['domain'] = (string) COOKIE_DOMAIN;
        }
        setcookie(MemberSessionService::COOKIE_NAME, '', $options);
        unset($_COOKIE[MemberSessionService::COOKIE_NAME]);
    }

    private function isSameOrigin(WP_REST_Request $request): bool
    {
        $origin = trim((string) $request->get_header('Origin'));
        if ($origin === '') {
            return true;
        }

        $originHost = wp_parse_url($origin, PHP_URL_HOST);
        $siteHost = wp_parse_url(home_url('/'), PHP_URL_HOST);
        $originScheme = strtolower((string) wp_parse_url($origin, PHP_URL_SCHEME));
        $siteScheme = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_SCHEME));
        $originPort = $this->effectivePort($originScheme, wp_parse_url($origin, PHP_URL_PORT));
        $sitePort = $this->effectivePort($siteScheme, wp_parse_url(home_url('/'), PHP_URL_PORT));

        return is_string($originHost)
            && is_string($siteHost)
            && strtolower($originHost) === strtolower($siteHost)
            && $originScheme === $siteScheme
            && $originPort === $sitePort;
    }

    private function effectivePort(string $scheme, $port): int
    {
        if (is_int($port) && $port > 0) {
            return $port;
        }

        return $scheme === 'https' ? 443 : ($scheme === 'http' ? 80 : 0);
    }

    private function success(array $data): WP_REST_Response
    {
        return $this->noStore(RestResponder::success($data));
    }

    private function error(MemberAuthException $error): WP_REST_Response
    {
        return $this->noStore(RestResponder::error($error->errorCode(), $error->getMessage(), $error->status(), $error->details()));
    }

    private function unexpected(): WP_REST_Response
    {
        return $this->noStore(RestResponder::error('member_portal_unavailable', __('Member access is temporarily unavailable.', 'coffeepos'), 503));
    }

    private function noStore(WP_REST_Response $response): WP_REST_Response
    {
        $response->header('Cache-Control', 'no-store, private');
        $response->header('Pragma', 'no-cache');

        return $response;
    }
}
