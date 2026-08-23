<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

interface PaymentGatewayInterface
{
    public function initialize(array $order, array $paymentContext): array;

    public function getStatus(array $order): array;

    public function verifyCompletion(array $order, array $trustedInput): array;
}
