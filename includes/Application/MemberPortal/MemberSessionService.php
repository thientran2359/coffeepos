<?php

declare(strict_types=1);

namespace CoffeePOS\Application\MemberPortal;

final class MemberSessionService
{
    public const COOKIE_NAME = 'coffeepos_member_session';

    public const META_SESSIONS = '_coffeepos_member_sessions';

    public const TTL = 2592000;

    private const MAX_SESSIONS = 5;

    public function create(int $customerId): array
    {
        if ($customerId <= 0) {
            throw new MemberAuthException('member_auth_failed', __('The phone number or PIN is incorrect.', 'coffeepos'), 401);
        }

        $selector = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));
        $selectorHash = $this->selectorHash($selector);
        $now = time();
        $expires = $now + self::TTL;
        $sessions = $this->sessions($customerId);

        $sessions[] = [
            'selector_hash' => $selectorHash,
            'created_at' => $now,
            'last_used_at' => $now,
            'expires_at' => $expires,
        ];

        usort($sessions, static function (array $left, array $right): int {
            return ((int) ($left['created_at'] ?? 0)) <=> ((int) ($right['created_at'] ?? 0));
        });

        while (count($sessions) > self::MAX_SESSIONS) {
            $removed = array_shift($sessions);
            if (is_array($removed) && ! empty($removed['selector_hash'])) {
                delete_transient($this->lookupKey((string) $removed['selector_hash']));
            }
        }

        if (update_user_meta($customerId, self::META_SESSIONS, array_values($sessions)) === false) {
            throw new MemberAuthException('member_portal_unavailable', __('Member access is temporarily unavailable.', 'coffeepos'), 503);
        }
        $stored = set_transient($this->lookupKey($selectorHash), [
            'customer_id' => $customerId,
            'validator_hash' => hash('sha256', $validator),
            'created_at' => $now,
            'expires_at' => $expires,
        ], self::TTL);
        if (! $stored) {
            $sessions = array_values(array_filter($sessions, static function (array $entry) use ($selectorHash): bool {
                return ! hash_equals((string) ($entry['selector_hash'] ?? ''), $selectorHash);
            }));
            update_user_meta($customerId, self::META_SESSIONS, $sessions);
            throw new MemberAuthException('member_portal_unavailable', __('Member access is temporarily unavailable.', 'coffeepos'), 503);
        }

        $token = $selector . '.' . $validator;

        return [
            'customer_id' => $customerId,
            'token' => $token,
            'csrf_token' => $this->csrfToken($token),
            'expires_at' => $expires,
        ];
    }

    public function authenticate(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || preg_match('/^[a-f0-9]{32}$/', $parts[0]) !== 1 || preg_match('/^[a-f0-9]{64}$/', $parts[1]) !== 1) {
            return null;
        }

        [$selector, $validator] = $parts;
        $selectorHash = $this->selectorHash($selector);
        $lookup = get_transient($this->lookupKey($selectorHash));
        if (! is_array($lookup) || (int) ($lookup['expires_at'] ?? 0) <= time()) {
            return null;
        }

        $customerId = absint($lookup['customer_id'] ?? 0);
        $expectedValidator = (string) ($lookup['validator_hash'] ?? '');
        if ($customerId <= 0 || $expectedValidator === '' || ! hash_equals($expectedValidator, hash('sha256', $validator))) {
            return null;
        }

        $sessions = $this->sessions($customerId);
        $found = false;
        foreach ($sessions as &$session) {
            if (! hash_equals((string) ($session['selector_hash'] ?? ''), $selectorHash)) {
                continue;
            }
            if ((int) ($session['expires_at'] ?? 0) <= time()) {
                break;
            }
            $session['last_used_at'] = time();
            $found = true;
            break;
        }
        unset($session);

        if (! $found) {
            delete_transient($this->lookupKey($selectorHash));
            return null;
        }

        update_user_meta($customerId, self::META_SESSIONS, $sessions);

        return [
            'customer_id' => $customerId,
            'selector_hash' => $selectorHash,
            'token' => $token,
            'csrf_token' => $this->csrfToken($token),
            'expires_at' => (int) $lookup['expires_at'],
        ];
    }

    public function verifyCsrf(array $session, string $submitted): bool
    {
        $expected = (string) ($session['csrf_token'] ?? '');

        return $expected !== '' && $submitted !== '' && hash_equals($expected, $submitted);
    }

    public function revokeCurrent(array $session): void
    {
        $customerId = absint($session['customer_id'] ?? 0);
        $selectorHash = (string) ($session['selector_hash'] ?? '');
        if ($customerId <= 0 || $selectorHash === '') {
            return;
        }

        $sessions = array_values(array_filter($this->sessions($customerId), static function (array $entry) use ($selectorHash): bool {
            return ! hash_equals((string) ($entry['selector_hash'] ?? ''), $selectorHash);
        }));
        update_user_meta($customerId, self::META_SESSIONS, $sessions);
        delete_transient($this->lookupKey($selectorHash));
    }

    public function revokeAll(int $customerId): void
    {
        foreach ($this->sessions($customerId) as $session) {
            $selectorHash = (string) ($session['selector_hash'] ?? '');
            if ($selectorHash !== '') {
                delete_transient($this->lookupKey($selectorHash));
            }
        }

        delete_user_meta($customerId, self::META_SESSIONS);
    }

    public static function cookiePath(): string
    {
        $path = defined('COOKIEPATH') ? (string) COOKIEPATH : '/';

        return $path !== '' ? $path : '/';
    }

    private function sessions(int $customerId): array
    {
        $stored = get_user_meta($customerId, self::META_SESSIONS, true);
        if (! is_array($stored)) {
            return [];
        }

        $now = time();
        return array_values(array_filter($stored, static function ($entry) use ($now): bool {
            return is_array($entry)
                && preg_match('/^[a-f0-9]{64}$/', (string) ($entry['selector_hash'] ?? '')) === 1
                && (int) ($entry['expires_at'] ?? 0) > $now;
        }));
    }

    private function selectorHash(string $selector): string
    {
        return hash_hmac('sha256', $selector, wp_salt('auth'));
    }

    private function lookupKey(string $selectorHash): string
    {
        return 'coffeepos_member_session_' . $selectorHash;
    }

    private function csrfToken(string $token): string
    {
        return hash_hmac('sha256', 'member-csrf|' . $token, wp_salt('nonce'));
    }
}
