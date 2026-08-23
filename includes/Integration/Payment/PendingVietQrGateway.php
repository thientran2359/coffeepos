<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\Payment;

use CoffeePOS\Application\Contracts\PaymentGatewayInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Infrastructure\Settings\Settings;

final class PendingVietQrGateway implements PaymentGatewayInterface
{
    public function initialize(array $order, array $paymentContext): array
    {
        $bank = trim((string) Settings::get(Settings::OPTION_VIETQR_BANK_ID));
        $account = preg_replace('/[^0-9A-Za-z]/', '', (string) Settings::get(Settings::OPTION_VIETQR_ACCOUNT_NUMBER));
        $name = trim((string) Settings::get(Settings::OPTION_VIETQR_ACCOUNT_NAME));
        $reference = trim((string) ($order['reference'] ?? ''));
        if ($reference === '') {
            $reference = 'POS-' . (string) ($order['number'] ?? $order['id'] ?? '');
        }
        $available = $bank !== '' && $account !== '';
        $qr = null;
        if ($available) {
            $qr = ['image_url' => 'https://vietqr.app/img?' . http_build_query([
                'acc' => $account,
                'bank' => $bank,
                'amount' => (string) ($order['total'] ?? ''),
                'des' => $reference,
                'holder' => $name,
                'store' => (string) get_bloginfo('name'),
                'template' => 'qronly',
                'showinfo' => 'false',
            ], '', '&', PHP_QUERY_RFC3986)];
        }
        return [
            'method' => 'bank_transfer', 'state' => 'awaiting_cashier_confirmation',
            'amount' => (string) ($order['total'] ?? '0'), 'reference' => $reference,
            'currency' => (string) ($order['currency'] ?? ''),
            'provider_available' => $available, 'qr' => $qr,
        ];
    }

    public function getStatus(array $order): array
    {
        return $this->initialize($order, []);
    }

    public function verifyCompletion(array $order, array $trustedInput): array
    {
        throw Phase01Exception::withCode(Phase01ErrorCodes::PAYMENT_VERIFICATION_FAILED, 'No trusted bank verification provider is configured.');
    }
}
