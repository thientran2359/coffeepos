<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

use CoffeePOS\Application\Contracts\CustomerGatewayInterface;
use WC_Customer;
use WP_User;

final class WooCommerceCustomerGateway implements CustomerGatewayInterface
{
    public function findById(int $customerId): ?array
    {
        if ($customerId <= 0) {
            return null;
        }

        if (! class_exists(WC_Customer::class)) {
            return null;
        }

        try {
            $customer = new WC_Customer($customerId);
        } catch (\Throwable $throwable) {
            return null;
        }

        if ($customer->get_id() !== $customerId) {
            return null;
        }

        return $this->mapCustomer($customer);
    }

    public function findByPhone(string $phone): ?array
    {
        $normalizedPhone = $this->normalizePhone($phone);

        if ($normalizedPhone === '') {
            return null;
        }

        $users = get_users([
            'number' => 50,
            'meta_key' => 'billing_phone',
            // The stored WooCommerce phone may contain spaces or separators.
            // Narrow by the last digits, then perform an exact normalized match.
            'meta_value' => substr($normalizedPhone, -4),
            'meta_compare' => 'LIKE',
        ]);

        $matches = [];

        foreach ($users as $user) {
            if (! $user instanceof WP_User) {
                continue;
            }

            $candidate = $this->findById((int) $user->ID);

            if ($candidate === null) {
                continue;
            }

            if ($this->normalizePhone((string) ($candidate['phone'] ?? '')) !== $normalizedPhone) {
                continue;
            }

            $matches[] = $candidate;
        }

        if (count($matches) > 1) {
            return [
                'ambiguous' => true,
                'candidates' => array_map(static function (array $customer): array {
                    return [
                        'id' => (int) $customer['id'],
                        'name' => (string) $customer['name'],
                        'phone' => (string) $customer['phone'],
                    ];
                }, $matches),
            ];
        }

        return $matches[0] ?? null;
    }

    private function mapCustomer(WC_Customer $customer): array
    {
        $firstName = $customer->get_first_name();
        $lastName = $customer->get_last_name();
        $displayName = trim($firstName . ' ' . $lastName);

        if ($displayName === '') {
            $displayName = $customer->get_display_name();
        }

        return [
            'id' => $customer->get_id(),
            'name' => $displayName,
            'phone' => $customer->get_billing_phone(),
        ];
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', trim($phone)) ?? '';
    }
}
