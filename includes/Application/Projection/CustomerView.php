<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Projection;

use CoffeePOS\Application\Customer\CustomerPhone;
use CoffeePOS\Domain\Customer\CustomerContext;

final class CustomerView
{
    private bool $guest;

    private ?int $id;

    private string $name;

    private string $phone;

    private string $email;

    private string $maskedPhone;

    private ?array $membership;

    private function __construct(
        bool $guest,
        ?int $id,
        string $name,
        string $phone,
        ?array $membership = null,
        string $email = '',
        string $maskedPhone = ''
    )
    {
        $this->guest = $guest;
        $this->id = $id;
        $this->name = trim($name);
        $this->phone = trim($phone);
        $this->email = trim($email);
        $this->maskedPhone = $maskedPhone !== '' ? $maskedPhone : CustomerPhone::mask($phone);
        $this->membership = $membership;
    }

    public static function guest(): self
    {
        return new self(true, null, '', '');
    }

    public static function member(
        int $id,
        string $name = '',
        string $phone = '',
        ?array $membership = null,
        string $email = '',
        string $maskedPhone = ''
    ): self
    {
        return new self(false, $id, $name, $phone, $membership, $email, $maskedPhone);
    }

    public static function fromDomain(CustomerContext $customerContext): self
    {
        if ($customerContext->isGuest()) {
            return self::guest();
        }

        return self::member(
            (int) $customerContext->customerId(),
            $customerContext->displayName(),
            $customerContext->phone(),
            $customerContext->membership()
        );
    }

    public function toArray(): array
    {
        return [
            'is_guest' => $this->guest,
            'mode' => $this->guest ? 'guest' : 'member',
            'id' => $this->id,
            'customer_id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->name,
            'phone' => $this->phone,
            'phone_masked' => $this->maskedPhone,
            'email' => $this->email,
            'membership' => $this->membership,
        ];
    }
}
