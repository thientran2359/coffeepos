<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Customer;

use CoffeePOS\Application\Contracts\CustomerGatewayInterface;
use CoffeePOS\Application\Contracts\CustomerCreationGatewayInterface;
use CoffeePOS\Application\Contracts\LockProviderInterface;
use CoffeePOS\Application\Contracts\MembershipProviderInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Projection\CustomerView;
use CoffeePOS\Domain\Customer\CustomerContext;

final class CustomerService
{
    private ?CustomerGatewayInterface $customerGateway;

    private ?MembershipProviderInterface $membershipProvider;

    private ?LockProviderInterface $lockProvider;

    public function __construct(
        ?CustomerGatewayInterface $customerGateway = null,
        ?MembershipProviderInterface $membershipProvider = null,
        ?LockProviderInterface $lockProvider = null
    )
    {
        $this->customerGateway = $customerGateway;
        $this->membershipProvider = $membershipProvider;
        $this->lockProvider = $lockProvider;
    }

    public function guestContext(): CustomerContext
    {
        return CustomerContext::guest();
    }

    public function findById(int $customerId): CustomerView
    {
        if ($customerId <= 0) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CUSTOMER,
                'Customer id must be greater than zero.'
            );
        }

        if ($this->customerGateway === null) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CONFIGURATION,
                'Customer gateway is not configured.'
            );
        }

        $customer = $this->customerGateway->findById($customerId);

        if ($customer === null) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::CUSTOMER_NOT_FOUND,
                'Customer not found.',
                ['customer_id' => $customerId]
            );
        }

        return $this->projectCustomer($customer);
    }

    public function projectCustomer(array $customer): CustomerView
    {
        $customerId = (int) ($customer['id'] ?? 0);

        if ($customerId <= 0) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CUSTOMER,
                'Customer id must be greater than zero.'
            );
        }

        $membership = null;

        if ($this->membershipProvider !== null) {
            try {
                $membership = $this->normalizeMembership(
                    $this->membershipProvider->membershipForCustomer($customer)
                );
            } catch (\Throwable $throwable) {
                $membership = null;
            }
        }

        return CustomerView::member(
            $customerId,
            (string) ($customer['name'] ?? ''),
            (string) ($customer['phone'] ?? ''),
            $membership,
            (string) ($customer['email'] ?? ''),
            CustomerPhone::mask((string) ($customer['phone'] ?? ''))
        );
    }

    public function findByPhone(string $phone, array $customers = []): CustomerView
    {
        $normalizedPhone = CustomerPhone::normalize($phone);

        if ($normalizedPhone === '') {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CUSTOMER_PHONE,
                'Enter a valid phone number.'
            );
        }

        if ($this->customerGateway !== null) {
            try {
                $customer = $this->customerGateway->findByPhone($normalizedPhone);
            } catch (Phase01Exception $exception) {
                throw $exception;
            } catch (\Throwable $throwable) {
                throw Phase01Exception::withCode(
                    Phase01ErrorCodes::CUSTOMER_LOOKUP_FAILED,
                    'Customer lookup failed.'
                );
            }

            if ($customer !== null) {
                if (! empty($customer['ambiguous'])) {
                    throw Phase01Exception::withCode(
                        Phase01ErrorCodes::CUSTOMER_PHONE_AMBIGUOUS,
                        'More than one customer uses this phone number.',
                        ['candidates' => array_values((array) ($customer['candidates'] ?? []))]
                    );
                }

                return $this->projectCustomer($customer);
            }
        }

        foreach ($customers as $customer) {
            if (! is_array($customer)) {
                continue;
            }

            $candidatePhone = CustomerPhone::normalize((string) ($customer['phone'] ?? ''));

            if ($candidatePhone !== $normalizedPhone) {
                continue;
            }

            return $this->projectCustomer($customer);
        }

        throw Phase01Exception::withCode(
            Phase01ErrorCodes::CUSTOMER_NOT_FOUND,
            'Customer not found by phone.',
            ['phone' => $phone]
        );
    }

    public function createMember(array $input): array
    {
        if (! $this->customerGateway instanceof CustomerCreationGatewayInterface) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CONFIGURATION,
                'Customer creation gateway is not configured.'
            );
        }

        if ($this->lockProvider === null) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CONFIGURATION,
                'Customer creation lock is not configured.'
            );
        }

        $displayName = $this->normalizeDisplayName((string) ($input['display_name'] ?? ''));
        $phone = CustomerPhone::normalize((string) ($input['phone'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $operationId = trim((string) ($input['client_operation_id'] ?? ''));

        if ($displayName === '') {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CUSTOMER_NAME,
                'Enter a member name.'
            );
        }

        $nameLength = function_exists('mb_strlen') ? mb_strlen($displayName) : strlen($displayName);
        if ($nameLength > 200) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CUSTOMER_NAME,
                'Member name must not exceed 200 characters.'
            );
        }

        if ($phone === '') {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CUSTOMER_PHONE,
                'Enter a valid phone number.'
            );
        }

        if ($email !== '' && (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CUSTOMER_EMAIL,
                'Enter a valid email address or leave it empty.'
            );
        }

        if (preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $operationId) !== 1) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CUSTOMER,
                'Invalid member creation operation id.'
            );
        }

        $normalized = [
            'display_name' => $displayName,
            'phone' => $phone,
            'email' => strtolower($email),
        ];
        $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $fingerprint = hash('sha256', is_string($encoded) ? $encoded : serialize($normalized));

        return $this->lockProvider->synchronized(
            'customer-phone:' . hash('sha256', $phone),
            function () use ($normalized, $operationId, $fingerprint): array {
                $gateway = $this->customerGateway;

                if (! $gateway instanceof CustomerCreationGatewayInterface) {
                    throw Phase01Exception::withCode(
                        Phase01ErrorCodes::INVALID_CONFIGURATION,
                        'Customer creation gateway is not configured.'
                    );
                }

                $replay = $gateway->findByCreationOperation($operationId);
                if ($replay !== null) {
                    if (! hash_equals((string) ($replay['creation_fingerprint'] ?? ''), $fingerprint)) {
                        throw Phase01Exception::withCode(
                            Phase01ErrorCodes::IDEMPOTENCY_KEY_REUSED,
                            'This member creation operation was already used with different data.'
                        );
                    }

                    return [
                        'customer' => $this->projectCustomer($replay),
                        'replayed' => true,
                    ];
                }

                $existing = $gateway->findByPhone($normalized['phone']);
                if ($existing !== null) {
                    if (! empty($existing['ambiguous'])) {
                        throw Phase01Exception::withCode(
                            Phase01ErrorCodes::CUSTOMER_PHONE_AMBIGUOUS,
                            'More than one customer uses this phone number.'
                        );
                    }

                    throw Phase01Exception::withCode(
                        Phase01ErrorCodes::CUSTOMER_PHONE_EXISTS,
                        'A member already uses this phone number.',
                        ['customer' => $this->projectCustomer($existing)->toArray()]
                    );
                }

                try {
                    return [
                        'customer' => $this->projectCustomer(
                            $gateway->createCustomer($normalized, $operationId, $fingerprint)
                        ),
                        'replayed' => false,
                    ];
                } catch (Phase01Exception $exception) {
                    throw $exception;
                } catch (\Throwable $throwable) {
                    throw Phase01Exception::withCode(
                        Phase01ErrorCodes::CUSTOMER_CREATE_FAILED,
                        'Member could not be created.'
                    );
                }
            }
        );
    }

    private function normalizeDisplayName(string $name): string
    {
        $value = trim(strip_tags($name));
        return preg_replace('/\s+/u', ' ', $value) ?? '';
    }

    private function normalizeMembership(?array $membership): ?array
    {
        if ($membership === null) {
            return null;
        }

        $projection = [];

        foreach (['status_label', 'tier_code', 'tier_label', 'points_display', 'balance_display'] as $field) {
            if (isset($membership[$field]) && is_scalar($membership[$field])) {
                $projection[$field] = trim((string) $membership[$field]);
            }
        }

        return $projection === [] ? null : $projection;
    }
}
