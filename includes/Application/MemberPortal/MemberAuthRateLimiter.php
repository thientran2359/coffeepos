<?php

declare(strict_types=1);

namespace CoffeePOS\Application\MemberPortal;

final class MemberAuthRateLimiter
{
    public const MAX_FAILURES = 5;

    public const WINDOW_SECONDS = 900;

    public function isLocked(string $normalizedPhone, ?int $now = null): array
    {
        $now = $now ?? time();
        $state = $this->state($normalizedPhone);

        if ($state === [] || (int) ($state['locked_until'] ?? 0) <= $now) {
            if ($state !== []) {
                $this->clear($normalizedPhone);
            }

            return ['locked' => false, 'retry_after' => 0];
        }

        return [
            'locked' => (int) ($state['failed_attempts'] ?? 0) >= self::MAX_FAILURES,
            'retry_after' => max(0, (int) $state['locked_until'] - $now),
        ];
    }

    public function recordFailure(string $normalizedPhone, ?int $now = null): array
    {
        $now = $now ?? time();
        $state = self::advanceState($this->state($normalizedPhone), $now);
        set_transient($this->key($normalizedPhone), $state, self::WINDOW_SECONDS);

        return [
            'locked' => (int) $state['failed_attempts'] >= self::MAX_FAILURES,
            'retry_after' => max(0, (int) $state['locked_until'] - $now),
        ];
    }

    public function clear(string $normalizedPhone): void
    {
        if ($normalizedPhone !== '') {
            delete_transient($this->key($normalizedPhone));
        }
    }

    public static function advanceState(array $state, int $now): array
    {
        $firstFailure = (int) ($state['first_failed_at'] ?? 0);
        $lockedUntil = (int) ($state['locked_until'] ?? 0);

        if ($firstFailure <= 0 || $lockedUntil <= $now) {
            $firstFailure = $now;
            $failures = 0;
        } else {
            $failures = max(0, (int) ($state['failed_attempts'] ?? 0));
        }

        return [
            'failed_attempts' => min(self::MAX_FAILURES, $failures + 1),
            'first_failed_at' => $firstFailure,
            'locked_until' => $firstFailure + self::WINDOW_SECONDS,
        ];
    }

    private function state(string $normalizedPhone): array
    {
        if ($normalizedPhone === '') {
            return [];
        }

        $state = get_transient($this->key($normalizedPhone));

        return is_array($state) ? $state : [];
    }

    private function key(string $normalizedPhone): string
    {
        return 'coffeepos_member_pin_' . hash_hmac('sha256', $normalizedPhone, wp_salt('auth'));
    }
}
