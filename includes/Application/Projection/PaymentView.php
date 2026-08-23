<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Projection;

use CoffeePOS\Domain\Payment\PaymentContext;

final class PaymentView
{
    private ?string $couponCode;

    private int $couponDiscountMinor;

    private function __construct(?string $couponCode, int $couponDiscountMinor)
    {
        $this->couponCode = $couponCode;
        $this->couponDiscountMinor = $couponDiscountMinor;
    }

    public static function fromDomain(PaymentContext $paymentContext): self
    {
        return new self($paymentContext->couponCode(), $paymentContext->couponDiscountMinor());
    }

    public function toArray(): array
    {
        return [
            'coupon_code' => $this->couponCode,
            'coupon_discount_minor' => $this->couponDiscountMinor,
        ];
    }
}
