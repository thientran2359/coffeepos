<?php

declare(strict_types=1);

namespace CoffeePOS\REST;

use CoffeePOS\Application\MemberPortal\MemberAuthException;
use CoffeePOS\Application\MemberPortal\MemberPinService;
use CoffeePOS\Support\Capabilities;
use CoffeePOS\Support\ErrorFactory;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class MemberManagementController
{
    private MemberPinService $pins;

    public function __construct(?MemberPinService $pins = null)
    {
        $this->pins = $pins ?? new MemberPinService();
    }

    public function register(string $namespace): void
    {
        register_rest_route($namespace, '/members/(?P<id>\d+)/temporary-pin', [[
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'generateTemporaryPin'],
            'permission_callback' => [$this, 'permissionCheck'],
        ]]);
    }

    public function permissionCheck()
    {
        return current_user_can(Capabilities::MANAGE_SETTINGS)
            ? true
            : ErrorFactory::forbidden('coffeepos_members_forbidden', __('You are not allowed to manage CoffeePOS members.', 'coffeepos'));
    }

    public function generateTemporaryPin(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $payload = $request->get_json_params();
            if (! is_array($payload) || empty($payload['confirmed'])) {
                return $this->noStore(RestResponder::error(
                    'member_pin_reset_confirmation_required',
                    __('Confirm that the current PIN and member sessions will be replaced.', 'coffeepos'),
                    400
                ));
            }

            $temporaryPin = $this->pins->generateTemporaryPin(absint($request['id']));

            return $this->noStore(RestResponder::success([
                'temporary_pin' => $temporaryPin,
                'must_change' => true,
                'message' => __('Temporary member PIN generated. It will not be shown again.', 'coffeepos'),
            ]));
        } catch (MemberAuthException $error) {
            return $this->noStore(RestResponder::error($error->errorCode(), $error->getMessage(), $error->status(), $error->details()));
        } catch (\Throwable $error) {
            return $this->noStore(RestResponder::error('member_pin_update_failed', __('The member PIN could not be saved.', 'coffeepos'), 500));
        }
    }

    private function noStore(WP_REST_Response $response): WP_REST_Response
    {
        $response->header('Cache-Control', 'no-store, private');
        $response->header('Pragma', 'no-cache');

        return $response;
    }
}
