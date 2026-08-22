<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Coupon;

use CoffeePOS\Application\Contracts\CouponGatewayInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Projection\CouponView;

final class CouponService
{
    private ?CouponGatewayInterface $couponGateway;

    public function __construct(?CouponGatewayInterface $couponGateway = null)
    {
        $this->couponGateway = $couponGateway;
    }

    public function validateCoupon(string $couponCode, array $context = []): CouponView
    {
        if ($this->couponGateway === null) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CONFIGURATION,
                'Coupon gateway is not configured.'
            );
        }

        $validationResult = $this->couponGateway->validate($couponCode, $context);

        return $this->validateCouponResult($couponCode, $validationResult);
    }

    public function requireValidCouponFromGateway(string $couponCode, array $context = []): CouponView
    {
        if ($this->couponGateway === null) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CONFIGURATION,
                'Coupon gateway is not configured.'
            );
        }

        $validationResult = $this->couponGateway->validate($couponCode, $context);

        return $this->requireValidCoupon($couponCode, $validationResult);
    }

    public function validateCouponResult(string $couponCode, array $validationResult): CouponView
    {
        $normalizedCode = strtoupper(trim($couponCode));

        if ($normalizedCode === '') {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_COUPON,
                'Coupon code is required.'
            );
        }

        if (! array_key_exists('is_valid', $validationResult)) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CONFIGURATION,
                'Coupon validation result is missing required key: is_valid.'
            );
        }

        $isValid = (bool) $validationResult['is_valid'];
        $message = (string) ($validationResult['message'] ?? '');

        if ($isValid) {
            return CouponView::valid($normalizedCode, $message);
        }

        return CouponView::invalid($normalizedCode, $message);
    }

    public function requireValidCoupon(string $couponCode, array $validationResult): CouponView
    {
        $couponView = $this->validateCouponResult($couponCode, $validationResult);
        $couponPayload = $couponView->toArray();

        if ((bool) $couponPayload['is_valid']) {
            return $couponView;
        }

        throw Phase01Exception::withCode(
            Phase01ErrorCodes::INVALID_COUPON,
            $couponPayload['message'] !== '' ? (string) $couponPayload['message'] : 'Coupon is invalid.',
            ['code' => $couponPayload['code']]
        );
    }
}
