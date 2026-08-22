<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

use CoffeePOS\Application\Contracts\CustomerGatewayInterface;
use WP_User;

final class WooCommerceCustomerGateway implements CustomerGatewayInterface
{
    public function findById(int $customerId): ?array
    {
        if ($customerId <= 0) {
            return null;
        }

        $user = get_user_by('id', $customerId);

        if (! $user instanceof WP_User) {
            return null;
        }

        return $this->mapUser($user);
    }

    public function findByPhone(string $phone): ?array
    {
        $normalizedPhone = $this->normalizePhone($phone);

        if ($normalizedPhone === '') {
            return null;
        }

        $users = get_users([
            'number' => 20,
            'meta_key' => 'billing_phone',
            'meta_value' => $phone,
            'meta_compare' => 'LIKE',
        ]);

        foreach ($users as $user) {
            if (! $user instanceof WP_User) {
                continue;
            }

            $candidate = $this->mapUser($user);

            if ($this->normalizePhone((string) ($candidate['phone'] ?? '')) !== $normalizedPhone) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    private function mapUser(WP_User $user): array
    {
        $firstName = (string) get_user_meta($user->ID, 'first_name', true);
        $lastName = (string) get_user_meta($user->ID, 'last_name', true);
        $displayName = trim($firstName . ' ' . $lastName);

        if ($displayName === '') {
            $displayName = $user->display_name;
        }

        return [
            'id' => (int) $user->ID,
            'name' => $displayName,
            'phone' => (string) get_user_meta($user->ID, 'billing_phone', true),
        ];
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', trim($phone)) ?? '';
    }
}
