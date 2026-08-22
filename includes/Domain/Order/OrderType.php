<?php

declare(strict_types=1);

namespace CoffeePOS\Domain\Order;

final class OrderType
{
    public const DINE_IN = 'dine_in';

    public const TAKEAWAY = 'takeaway';

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, [self::DINE_IN, self::TAKEAWAY], true)) {
            throw new \InvalidArgumentException('Invalid order type.');
        }

        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self(trim($value));
    }

    public static function dineIn(): self
    {
        return new self(self::DINE_IN);
    }

    public static function takeaway(): self
    {
        return new self(self::TAKEAWAY);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isDineIn(): bool
    {
        return $this->value === self::DINE_IN;
    }

    public function isTakeaway(): bool
    {
        return $this->value === self::TAKEAWAY;
    }
}
