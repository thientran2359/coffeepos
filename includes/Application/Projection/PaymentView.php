<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Projection;

use CoffeePOS\Domain\Payment\PaymentContext;

final class PaymentView
{
    private ?string $couponCode;

    private function __construct(?string $couponCode)
    {
        $this->couponCode = $couponCode;
    }

    public static function fromDomain(PaymentContext $paymentContext): self
    {
        return new self($paymentContext->couponCode());
    }

    public function toArray(): array
    {
        return [
            'coupon_code' => $this->couponCode,
        ];
    }
}
