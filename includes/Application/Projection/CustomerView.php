<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Projection;

use CoffeePOS\Domain\Customer\CustomerContext;

final class CustomerView
{
    private bool $guest;

    private ?int $id;

    private string $name;

    private string $phone;

    private function __construct(bool $guest, ?int $id, string $name, string $phone)
    {
        $this->guest = $guest;
        $this->id = $id;
        $this->name = trim($name);
        $this->phone = trim($phone);
    }

    public static function guest(): self
    {
        return new self(true, null, '', '');
    }

    public static function member(int $id, string $name = '', string $phone = ''): self
    {
        return new self(false, $id, $name, $phone);
    }

    public static function fromDomain(CustomerContext $customerContext): self
    {
        if ($customerContext->isGuest()) {
            return self::guest();
        }

        return self::member(
            (int) $customerContext->customerId(),
            $customerContext->displayName(),
            $customerContext->phone()
        );
    }

    public function toArray(): array
    {
        return [
            'is_guest' => $this->guest,
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
        ];
    }
}
