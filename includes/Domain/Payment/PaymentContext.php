<?php

declare(strict_types=1);

namespace CoffeePOS\Domain\Payment;

final class PaymentContext
{
    private ?string $couponCode;

    private function __construct(?string $couponCode)
    {
        $this->couponCode = $couponCode;
    }

    public static function none(): self
    {
        return new self(null);
    }

    public static function withCoupon(string $couponCode): self
    {
        $normalized = strtoupper(trim($couponCode));

        if ($normalized === '') {
            throw new \InvalidArgumentException('Coupon code cannot be empty.');
        }

        return new self($normalized);
    }

    public function hasCoupon(): bool
    {
        return $this->couponCode !== null;
    }

    public function couponCode(): ?string
    {
        return $this->couponCode;
    }

    public function toArray(): array
    {
        return [
            'coupon_code' => $this->couponCode,
        ];
    }
}
