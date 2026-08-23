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
        $template = trim((string) Settings::get(Settings::OPTION_VIETQR_TEMPLATE)) ?: 'compact2';
        $reference = 'POS-' . (string) ($order['number'] ?? $order['id'] ?? '');
        $available = $bank !== '' && $account !== '';
        $qr = null;
        if ($available) {
            $base = 'https://img.vietqr.io/image/' . rawurlencode($bank . '-' . $account . '-' . $template) . '.png';
            $qr = ['image_url' => $base . '?' . http_build_query([
                'amount' => (string) ($order['total'] ?? ''),
                'addInfo' => $reference,
                'accountName' => $name,
            ], '', '&', PHP_QUERY_RFC3986)];
        }
        return [
            'method' => 'bank_transfer', 'state' => 'pending',
            'amount' => (string) ($order['total'] ?? '0'), 'reference' => $reference,
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
