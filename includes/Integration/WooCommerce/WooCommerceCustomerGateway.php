<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

use CoffeePOS\Application\Contracts\CustomerCreationGatewayInterface;
use CoffeePOS\Application\Customer\CustomerPhone;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use WC_Customer;
use WP_User;

final class WooCommerceCustomerGateway implements CustomerCreationGatewayInterface
{
    private const META_OPERATION_ID = '_coffeepos_member_create_operation_id';

    private const META_OPERATION_FINGERPRINT = '_coffeepos_member_create_fingerprint';

    private const META_PLACEHOLDER_EMAIL = '_coffeepos_placeholder_email';

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
        $normalizedPhone = CustomerPhone::normalize($phone);

        if ($normalizedPhone === '') {
            return null;
        }

        $users = get_users([
            'number' => -1,
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

            if (CustomerPhone::normalize((string) ($candidate['phone'] ?? '')) !== $normalizedPhone) {
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
                        'phone_masked' => CustomerPhone::mask((string) $customer['phone']),
                    ];
                }, $matches),
            ];
        }

        return $matches[0] ?? null;
    }

    public function findByCreationOperation(string $operationId): ?array
    {
        if ($operationId === '' || ! function_exists('get_users')) {
            return null;
        }

        $users = get_users([
            'number' => 2,
            'meta_key' => self::META_OPERATION_ID,
            'meta_value' => $operationId,
            'meta_compare' => '=',
        ]);

        foreach ($users as $user) {
            if (! $user instanceof WP_User) {
                continue;
            }

            $customer = $this->findById((int) $user->ID);
            if ($customer !== null) {
                return $customer;
            }
        }

        return null;
    }

    public function createCustomer(array $customer, string $operationId, string $fingerprint): array
    {
        if (! class_exists(WC_Customer::class)) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::CUSTOMER_CREATE_FAILED,
                'WooCommerce customer API is unavailable.'
            );
        }

        $displayName = (string) ($customer['display_name'] ?? '');
        $phone = CustomerPhone::normalize((string) ($customer['phone'] ?? ''));
        $email = strtolower(trim((string) ($customer['email'] ?? '')));
        $placeholderEmail = $email === '';

        if (! $placeholderEmail && function_exists('email_exists') && email_exists($email)) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CUSTOMER_EMAIL,
                'This email address is already registered.'
            );
        }

        if ($placeholderEmail) {
            $email = 'member-' . substr(hash('sha256', $phone), 0, 20) . '@example.invalid';
        }

        $parts = preg_split('/\s+/u', trim($displayName), 2) ?: [];
        $firstName = (string) ($parts[0] ?? $displayName);
        $lastName = (string) ($parts[1] ?? '');
        $usernameBase = 'coffeepos_' . substr(hash('sha256', $phone), 0, 20);
        $username = $usernameBase;
        $suffix = 0;

        while (function_exists('username_exists') && username_exists($username)) {
            $suffix++;
            $username = $usernameBase . '_' . $suffix;
        }

        try {
            $record = new WC_Customer();
            $record->set_username($username);
            $record->set_password(function_exists('wp_generate_password') ? wp_generate_password(32, true, true) : bin2hex(random_bytes(16)));
            $record->set_email($email);
            $record->set_first_name($firstName);
            $record->set_last_name($lastName);
            $record->set_display_name($displayName);
            $record->set_billing_first_name($firstName);
            $record->set_billing_last_name($lastName);
            $record->set_billing_phone($phone);
            if (! $placeholderEmail) {
                $record->set_billing_email($email);
            }
            $record->add_meta_data(self::META_OPERATION_ID, $operationId, true);
            $record->add_meta_data(self::META_OPERATION_FINGERPRINT, $fingerprint, true);
            $record->add_meta_data(self::META_PLACEHOLDER_EMAIL, $placeholderEmail ? 'yes' : 'no', true);
            $record->save();

            $created = $this->findById((int) $record->get_id());
            if ($created === null) {
                throw new \RuntimeException('Created customer could not be reloaded.');
            }

            return $created;
        } catch (Phase01Exception $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::CUSTOMER_CREATE_FAILED,
                'WooCommerce could not create the member.'
            );
        }
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
            'email' => $customer->get_meta(self::META_PLACEHOLDER_EMAIL, true) === 'yes'
                ? ''
                : (string) ($customer->get_billing_email() ?: $customer->get_email()),
            'creation_operation_id' => (string) $customer->get_meta(self::META_OPERATION_ID, true),
            'creation_fingerprint' => (string) $customer->get_meta(self::META_OPERATION_FINGERPRINT, true),
        ];
    }
}
