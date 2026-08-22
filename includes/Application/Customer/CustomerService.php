<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Customer;

use CoffeePOS\Application\Contracts\CustomerGatewayInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Projection\CustomerView;
use CoffeePOS\Domain\Customer\CustomerContext;

final class CustomerService
{
    private ?CustomerGatewayInterface $customerGateway;

    public function __construct(?CustomerGatewayInterface $customerGateway = null)
    {
        $this->customerGateway = $customerGateway;
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

        return CustomerView::member(
            $customerId,
            (string) ($customer['name'] ?? ''),
            (string) ($customer['phone'] ?? '')
        );
    }

    public function findByPhone(string $phone, array $customers = []): CustomerView
    {
        $normalizedPhone = $this->normalizePhone($phone);

        if ($normalizedPhone === '') {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CUSTOMER,
                'Phone number is required for customer lookup.'
            );
        }

        if ($this->customerGateway !== null) {
            $customer = $this->customerGateway->findByPhone($normalizedPhone);

            if ($customer !== null) {
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
}
