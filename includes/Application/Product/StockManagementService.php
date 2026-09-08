<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Product;

use CoffeePOS\Application\Contracts\StockManagementGatewayInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;

final class StockManagementService
{
    private StockManagementGatewayInterface $gateway;

    public function __construct(StockManagementGatewayInterface $gateway)
    {
        $this->gateway = $gateway;
    }

    public function projection(int $productId): array
    {
        $projection = $this->gateway->projection($productId);

        if ($projection === null) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_PRODUCT, 'Product is unavailable.');
        }

        return $projection;
    }

    public function update(int $productId, array $payload): array
    {
        $projection = $this->projection($productId);
        $targetId = (int) ($payload['target_id'] ?? 0);
        $mode = (string) ($payload['mode'] ?? '');
        $reason = trim((string) ($payload['reason'] ?? ''));
        $target = null;

        foreach ($projection['targets'] as $candidate) {
            if ((int) $candidate['id'] === $targetId) {
                $target = $candidate;
                break;
            }
        }

        if ($target === null) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_STOCK_ADJUSTMENT, 'Stock target is invalid.');
        }

        $reasonLength = preg_match_all('/./us', $reason, $reasonCharacters);

        if ($reasonLength === false || $reasonLength < 3 || $reasonLength > 200) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_STOCK_ADJUSTMENT, 'A stock adjustment reason is required.');
        }

        if ($mode === 'quantity' && ! empty($target['manages_quantity'])) {
            $rawQuantity = $payload['quantity'] ?? null;

            if (! is_numeric($rawQuantity) || (int) $rawQuantity < 0 || (string) (int) $rawQuantity !== trim((string) $rawQuantity)) {
                throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_STOCK_ADJUSTMENT, 'Stock quantity must be a non-negative whole number.');
            }

            $updated = $this->gateway->setQuantity($targetId, (int) $rawQuantity);
        } elseif ($mode === 'status' && empty($target['manages_quantity'])) {
            $status = (string) ($payload['stock_status'] ?? '');

            if (! in_array($status, ['instock', 'outofstock'], true)) {
                throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_STOCK_ADJUSTMENT, 'Stock status is invalid.');
            }

            $updated = $this->gateway->setStatus($targetId, $status);
        } else {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_STOCK_ADJUSTMENT, 'Stock adjustment mode does not match this product.');
        }

        return [
            'stock' => $this->projection($productId),
            'updated_target' => $updated,
            'reason' => $reason,
        ];
    }
}
