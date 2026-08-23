<?php

declare(strict_types=1);

namespace CoffeePOS\Domain\Payment;

final class PaymentContext
{
    private ?string $couponCode;

    private int $couponDiscountMinor;

    private function __construct(?string $couponCode, int $couponDiscountMinor = 0)
    {
        $this->couponCode = $couponCode;
        $this->couponDiscountMinor = max(0, $couponDiscountMinor);
    }

    public static function none(): self
    {
        return new self(null);
    }

    public static function withCoupon(string $couponCode, int $discountMinor = 0): self
    {
        $normalized = strtoupper(trim($couponCode));

        if ($normalized === '') {
            throw new \InvalidArgumentException('Coupon code cannot be empty.');
        }

        return new self($normalized, $discountMinor);
    }

    public function hasCoupon(): bool
    {
        return $this->couponCode !== null;
    }

    public function couponCode(): ?string
    {
        return $this->couponCode;
    }

    public function couponDiscountMinor(): int
    {
        return $this->couponDiscountMinor;
    }

    public function toArray(): array
    {
        return [
            'coupon_code' => $this->couponCode,
            'coupon_discount_minor' => $this->couponDiscountMinor,
        ];
    }
}
