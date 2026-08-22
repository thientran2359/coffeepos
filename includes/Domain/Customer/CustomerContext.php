<?php

declare(strict_types=1);

namespace CoffeePOS\Domain\Customer;

final class CustomerContext
{
    private bool $guest;

    private ?int $customerId;

    private string $phone;

    private string $displayName;

    private function __construct(bool $guest, ?int $customerId, string $phone, string $displayName)
    {
        $this->guest = $guest;
        $this->customerId = $customerId;
        $this->phone = $phone;
        $this->displayName = $displayName;
    }

    public static function guest(): self
    {
        return new self(true, null, '', '');
    }

    public static function member(int $customerId, string $phone = '', string $displayName = ''): self
    {
        if ($customerId <= 0) {
            throw new \InvalidArgumentException('Customer id must be greater than zero.');
        }

        return new self(false, $customerId, trim($phone), trim($displayName));
    }

    public function isGuest(): bool
    {
        return $this->guest;
    }

    public function customerId(): ?int
    {
        return $this->customerId;
    }

    public function phone(): string
    {
        return $this->phone;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function toArray(): array
    {
        return [
            'is_guest' => $this->guest,
            'customer_id' => $this->customerId,
            'phone' => $this->phone,
            'display_name' => $this->displayName,
        ];
    }
}
