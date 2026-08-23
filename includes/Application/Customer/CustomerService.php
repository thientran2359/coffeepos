<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Customer;

use CoffeePOS\Application\Contracts\CustomerGatewayInterface;
use CoffeePOS\Application\Contracts\MembershipProviderInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Projection\CustomerView;
use CoffeePOS\Domain\Customer\CustomerContext;

final class CustomerService
{
    private ?CustomerGatewayInterface $customerGateway;

    private ?MembershipProviderInterface $membershipProvider;

    public function __construct(
        ?CustomerGatewayInterface $customerGateway = null,
        ?MembershipProviderInterface $membershipProvider = null
    )
    {
        $this->customerGateway = $customerGateway;
        $this->membershipProvider = $membershipProvider;
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
            $membership
        );
    }

    public function findByPhone(string $phone, array $customers = []): CustomerView
    {
        $normalizedPhone = $this->normalizePhone($phone);

        if (strlen($normalizedPhone) < 7 || strlen($normalizedPhone) > 15) {
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

            $candidatePhone = $this->normalizePhone((string) ($customer['phone'] ?? ''));

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

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', trim($phone)) ?? '';
    }

    private function normalizeMembership(?array $membership): ?array
    {
        if ($membership === null) {
            return null;
        }

        $projection = [];

        foreach (['status_label', 'tier_label', 'points_display', 'balance_display'] as $field) {
            if (isset($membership[$field]) && is_scalar($membership[$field])) {
                $projection[$field] = trim((string) $membership[$field]);
            }
        }

        return $projection === [] ? null : $projection;
    }
}
