<?php

declare(strict_types=1);

namespace CoffeePOS\Application\MemberPortal;

use CoffeePOS\Application\Customer\CustomerPhone;
use CoffeePOS\Integration\WooCommerce\WooCommerceCustomerGateway;

final class MemberPinService
{
    public const META_PIN_HASH = '_coffeepos_member_pin_hash';

    public const META_MUST_CHANGE = '_coffeepos_member_pin_must_change';

    private MemberSessionService $sessions;

    private MemberAuthRateLimiter $limiter;

    public function __construct(?MemberSessionService $sessions = null, ?MemberAuthRateLimiter $limiter = null)
    {
        $this->sessions = $sessions ?? new MemberSessionService();
        $this->limiter = $limiter ?? new MemberAuthRateLimiter();
    }

    public function generateTemporaryPin(int $customerId): string
    {
        $customer = (new WooCommerceCustomerGateway())->findById($customerId);
        $phone = CustomerPhone::normalize((string) ($customer['phone'] ?? ''));
        if ($customer === null || $phone === '') {
            throw new MemberAuthException('member_auth_failed', __('Member was not found.', 'coffeepos'), 404);
        }

        $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hash = wp_hash_password($pin);
        if (! is_string($hash) || $hash === '') {
            throw new MemberAuthException('member_pin_update_failed', __('The member PIN could not be saved.', 'coffeepos'), 500);
        }

        update_user_meta($customerId, self::META_PIN_HASH, $hash);
        update_user_meta($customerId, self::META_MUST_CHANGE, 'yes');
        if ((string) get_user_meta($customerId, self::META_PIN_HASH, true) !== $hash || ! $this->mustChange($customerId)) {
            throw new MemberAuthException('member_pin_update_failed', __('The member PIN could not be saved.', 'coffeepos'), 500);
        }
        $this->sessions->revokeAll($customerId);
        $this->limiter->clear($phone);

        return $pin;
    }

    public function hasPin(int $customerId): bool
    {
        return trim((string) get_user_meta($customerId, self::META_PIN_HASH, true)) !== '';
    }

    public function mustChange(int $customerId): bool
    {
        return get_user_meta($customerId, self::META_MUST_CHANGE, true) === 'yes';
    }

    public function verify(int $customerId, string $pin): bool
    {
        $hash = (string) get_user_meta($customerId, self::META_PIN_HASH, true);

        // This is a CoffeePOS-only hash. Omitting user_id prevents WordPress
        // password rehash logic from ever replacing the customer's WP password.
        return $hash !== '' && wp_check_password($pin, $hash);
    }

    public function verifyDummy(string $pin): void
    {
        static $dummyHash = null;
        if (! is_string($dummyHash)) {
            $dummyHash = wp_hash_password('not-a-member-pin-' . wp_salt('auth'));
        }
        wp_check_password($pin, $dummyHash);
    }

    public function changePin(int $customerId, string $newPin): void
    {
        if (preg_match('/^[0-9]{6}$/', $newPin) !== 1) {
            throw new MemberAuthException('invalid_member_pin', __('Enter a six-digit PIN.', 'coffeepos'), 400);
        }

        if ($this->verify($customerId, $newPin)) {
            throw new MemberAuthException('member_pin_unchanged', __('Choose a PIN different from the temporary PIN.', 'coffeepos'), 409);
        }

        $hash = wp_hash_password($newPin);
        if (! is_string($hash) || $hash === '') {
            throw new MemberAuthException('member_pin_update_failed', __('The member PIN could not be saved.', 'coffeepos'), 500);
        }

        update_user_meta($customerId, self::META_PIN_HASH, $hash);
        update_user_meta($customerId, self::META_MUST_CHANGE, 'no');
        if ((string) get_user_meta($customerId, self::META_PIN_HASH, true) !== $hash || $this->mustChange($customerId)) {
            throw new MemberAuthException('member_pin_update_failed', __('The member PIN could not be saved.', 'coffeepos'), 500);
        }
        $this->sessions->revokeAll($customerId);
    }
}
