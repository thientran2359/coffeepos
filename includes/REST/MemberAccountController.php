<?php

declare(strict_types=1);

namespace CoffeePOS\REST;

use CoffeePOS\Application\MemberPortal\MemberAuthException;
use CoffeePOS\Application\MemberPortal\MemberAuthService;
use CoffeePOS\Application\MemberPortal\MemberPortalService;
use CoffeePOS\Application\MemberPortal\MemberSessionService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class MemberAccountController
{
    private MemberAuthService $auth;

    private MemberPortalService $portal;

    public function __construct(?MemberAuthService $auth = null, ?MemberPortalService $portal = null)
    {
        $this->auth = $auth ?? new MemberAuthService();
        $this->portal = $portal ?? new MemberPortalService();
    }

    public function register(string $namespace): void
    {
        register_rest_route($namespace, '/member/account', [[
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'account'],
            'permission_callback' => '__return_true',
        ]]);
        register_rest_route($namespace, '/member/orders', [[
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'orders'],
            'permission_callback' => '__return_true',
        ]]);
        register_rest_route($namespace, '/member/orders/(?P<id>\d+)', [[
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'order'],
            'permission_callback' => '__return_true',
        ]]);
    }

    public function account(WP_REST_Request $request): WP_REST_Response
    {
        return $this->respond(function (array $session): array {
            return $this->portal->account((int) $session['customer_id']);
        });
    }

    public function orders(WP_REST_Request $request): WP_REST_Response
    {
        return $this->respond(function (array $session) use ($request): array {
            return $this->portal->orders((int) $session['customer_id'], $request->get_params());
        });
    }

    public function order(WP_REST_Request $request): WP_REST_Response
    {
        return $this->respond(function (array $session) use ($request): array {
            return ['order' => $this->portal->order((int) $session['customer_id'], absint($request['id']))];
        });
    }

    private function respond(callable $callback): WP_REST_Response
    {
        try {
            $token = isset($_COOKIE[MemberSessionService::COOKIE_NAME]) && is_string($_COOKIE[MemberSessionService::COOKIE_NAME])
                ? trim(wp_unslash($_COOKIE[MemberSessionService::COOKIE_NAME]))
                : '';
            $session = $this->auth->session($token);
            if (! empty($session['pin_change_required'])) {
                throw new MemberAuthException('member_pin_change_required', __('Change your temporary PIN before viewing member information.', 'coffeepos'), 403);
            }

            return $this->noStore(RestResponder::success($callback($session)));
        } catch (MemberAuthException $error) {
            return $this->noStore(RestResponder::error($error->errorCode(), $error->getMessage(), $error->status(), $error->details()));
        } catch (\Throwable $error) {
            return $this->noStore(RestResponder::error('member_orders_unavailable', __('Member information is temporarily unavailable.', 'coffeepos'), 503));
        }
    }

    private function noStore(WP_REST_Response $response): WP_REST_Response
    {
        $response->header('Cache-Control', 'no-store, private');
        $response->header('Pragma', 'no-cache');

        return $response;
    }
}
