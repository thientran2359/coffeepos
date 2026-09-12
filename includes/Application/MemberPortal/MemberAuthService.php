<?php

declare(strict_types=1);

namespace CoffeePOS\Application\MemberPortal;

use CoffeePOS\Application\Customer\CustomerPhone;
use CoffeePOS\Infrastructure\Settings\Settings;
use CoffeePOS\Integration\WooCommerce\WooCommerceCustomerGateway;

final class MemberAuthService
{
    private WooCommerceCustomerGateway $customers;

    private MemberPinService $pins;

    private MemberSessionService $sessions;

    private MemberAuthRateLimiter $limiter;

    public function __construct(
        ?WooCommerceCustomerGateway $customers = null,
        ?MemberPinService $pins = null,
        ?MemberSessionService $sessions = null,
        ?MemberAuthRateLimiter $limiter = null
    ) {
        $this->customers = $customers ?? new WooCommerceCustomerGateway();
        $this->sessions = $sessions ?? new MemberSessionService();
        $this->limiter = $limiter ?? new MemberAuthRateLimiter();
        $this->pins = $pins ?? new MemberPinService($this->sessions, $this->limiter);
    }

    public function login(string $phone, string $pin): array
    {
        if (! (bool) Settings::get(Settings::OPTION_MEMBERSHIP_ENABLED)) {
            throw new MemberAuthException('member_portal_unavailable', __('Member access is temporarily unavailable.', 'coffeepos'), 503);
        }

        $normalizedPhone = CustomerPhone::normalize($phone);
        if ($normalizedPhone === '') {
            throw new MemberAuthException('invalid_member_phone', __('Enter a valid phone number.', 'coffeepos'), 400);
        }
        if (preg_match('/^[0-9]{6}$/', $pin) !== 1) {
            throw new MemberAuthException('invalid_member_pin', __('Enter a six-digit PIN.', 'coffeepos'), 400);
        }

        $locked = $this->limiter->isLocked($normalizedPhone);
        if (! empty($locked['locked'])) {
            throw $this->locked((int) $locked['retry_after']);
        }

        $customer = $this->customers->findByPhone($normalizedPhone);
        $customerId = is_array($customer) && empty($customer['ambiguous']) ? absint($customer['id'] ?? 0) : 0;
        $verified = $customerId > 0 && $this->pins->verify($customerId, $pin);

        if (! $verified) {
            if ($customerId <= 0) {
                $this->pins->verifyDummy($pin);
            }
            $failure = $this->limiter->recordFailure($normalizedPhone);
            if (! empty($failure['locked'])) {
                throw $this->locked((int) $failure['retry_after']);
            }
            throw new MemberAuthException('member_auth_failed', __('The phone number or PIN is incorrect.', 'coffeepos'), 401);
        }

        $this->limiter->clear($normalizedPhone);
        $session = $this->sessions->create($customerId);
        $session['pin_change_required'] = $this->pins->mustChange($customerId);

        return $session;
    }

    public function session(string $token): array
    {
        $session = $this->sessions->authenticate($token);
        if ($session === null) {
            throw new MemberAuthException('member_session_required', __('Your member session has expired. Please sign in again.', 'coffeepos'), 401);
        }

        $session['pin_change_required'] = $this->pins->mustChange((int) $session['customer_id']);

        return $session;
    }

    public function changePin(array $session, string $csrf, string $newPin, string $confirmation): array
    {
        if (! $this->sessions->verifyCsrf($session, $csrf)) {
            throw new MemberAuthException('member_csrf_invalid', __('The member form expired. Please try again.', 'coffeepos'), 403);
        }
        if ($newPin !== $confirmation) {
            throw new MemberAuthException('invalid_member_pin_confirmation', __('The PIN confirmation does not match.', 'coffeepos'), 400);
        }

        $customerId = (int) $session['customer_id'];
        $this->pins->changePin($customerId, $newPin);
        $newSession = $this->sessions->create($customerId);
        $newSession['pin_change_required'] = false;

        return $newSession;
    }

    public function logout(array $session, string $csrf): void
    {
        if (! $this->sessions->verifyCsrf($session, $csrf)) {
            throw new MemberAuthException('member_csrf_invalid', __('The member form expired. Please try again.', 'coffeepos'), 403);
        }

        $this->sessions->revokeCurrent($session);
    }

    private function locked(int $retryAfter): MemberAuthException
    {
        return new MemberAuthException(
            'member_auth_locked',
            __('Too many failed attempts. Please try again later.', 'coffeepos'),
            429,
            ['retry_after' => max(1, $retryAfter)]
        );
    }
}
