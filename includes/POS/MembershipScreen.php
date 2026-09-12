<?php

declare(strict_types=1);

namespace CoffeePOS\POS;

use CoffeePOS\Application\MemberPortal\MemberPinService;
use CoffeePOS\Integration\WooCommerce\WooCommerceMemberDirectory;
use CoffeePOS\Support\Capabilities;

final class MembershipScreen
{
    public static function handleRequest(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return [];
        }

        if (! current_user_can(Capabilities::MANAGE_SETTINGS)) {
            wp_die(
                esc_html__('You are not allowed to manage CoffeePOS members.', 'coffeepos'),
                esc_html__('Forbidden', 'coffeepos'),
                ['response' => 403]
            );
        }

        $nonce = isset($_POST['coffeepos_membership_nonce'])
            ? sanitize_text_field(wp_unslash((string) $_POST['coffeepos_membership_nonce']))
            : '';

        if (! wp_verify_nonce($nonce, 'coffeepos_manage_membership')) {
            return ['type' => 'error', 'message' => __('The membership form expired. Please try again.', 'coffeepos')];
        }

        $input = wp_unslash($_POST);
        try {
            $action = sanitize_key((string) ($input['membership_action'] ?? ''));
            if ($action === 'member-pin-reset') {
                if (empty($input['confirm_pin_reset'])) {
                    throw new \RuntimeException(__('Confirm that the current PIN and member sessions will be replaced.', 'coffeepos'));
                }
                $memberId = absint($input['customer_id'] ?? 0);
                $temporaryPin = (new MemberPinService())->generateTemporaryPin($memberId);
                nocache_headers();
                return [
                    'type' => 'success',
                    'message' => __('Temporary member PIN generated. It will not be shown again.', 'coffeepos'),
                    'member_id' => $memberId,
                    'temporary_pin' => $temporaryPin,
                ];
            }
            if ($action !== 'member-update') {
                throw new \RuntimeException(__('Invalid membership action.', 'coffeepos'));
            }
            $memberId = (new WooCommerceMemberDirectory())->update($input);
            return ['type' => 'success', 'message' => __('Member updated.', 'coffeepos'), 'member_id' => $memberId, 'return_to_list' => ! empty($input['return_to_list'])];
        } catch (\Throwable $error) {
            return ['type' => 'error', 'message' => $error instanceof \RuntimeException ? $error->getMessage() : __('Membership update failed.', 'coffeepos'), 'member_id' => absint($input['customer_id'] ?? 0), 'return_to_list' => ! empty($input['return_to_list'])];
        }
    }

    public static function data(array $notice = []): array
    {
        $page = max(1, absint($_GET['member_page'] ?? 1));
        $search = sanitize_text_field(wp_unslash((string) ($_GET['member_search'] ?? '')));
        $memberId = ! empty($notice['return_to_list'])
            ? 0
            : max(0, (int) ($notice['member_id'] ?? absint($_GET['member_id'] ?? 0)));
        $directory = new WooCommerceMemberDirectory();
        return ['list' => $directory->list($page, $search), 'detail' => $memberId > 0 ? $directory->detail($memberId) : null];
    }
}
