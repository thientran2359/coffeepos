<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Product;

use CoffeePOS\Application\Contracts\ProductGatewayInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Projection\ProductView;

final class ProductService
{
    private ?ProductGatewayInterface $productGateway;

    public function __construct(?ProductGatewayInterface $productGateway = null)
    {
        $this->productGateway = $productGateway;
    }

    public function getById(int $productId): ProductView
    {
        if ($productId <= 0) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_PRODUCT,
                'Product id must be greater than zero.'
            );
        }

        if ($this->productGateway === null) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CONFIGURATION,
                'Product gateway is not configured.'
            );
        }

        $product = $this->productGateway->findById($productId);

        if ($product === null) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_PRODUCT,
                'Product not found.',
                ['product_id' => $productId]
            );
        }

        return $this->projectProduct($product);
    }

    public function search(array $criteria = []): array
    {
        if ($this->productGateway === null) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CONFIGURATION,
                'Product gateway is not configured.'
            );
        }

        $products = $this->productGateway->search($criteria);

        return $this->projectProducts($products);
    }

    public function projectProduct(array $product): ProductView
    {
        $productId = isset($product['id']) ? (int) $product['id'] : 0;

        if ($productId <= 0) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_PRODUCT,
                'Product id must be greater than zero.'
            );
        }

        $currency = strtoupper(trim((string) ($product['currency'] ?? '')));

        if ($currency === '') {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CONFIGURATION,
                'Currency is required for product projection.',
                ['product_id' => $productId]
            );
        }

        return new ProductView(
            $productId,
            (string) ($product['name'] ?? ''),
            (int) ($product['price_minor'] ?? 0),
            $currency,
            (bool) ($product['is_variable'] ?? false),
            (bool) ($product['is_in_stock'] ?? false),
            (string) ($product['image_url'] ?? '')
        );
    }

    public function projectProducts(array $products): array
    {
        $views = [];

        foreach ($products as $product) {
            if (! is_array($product)) {
                throw Phase01Exception::withCode(
                    Phase01ErrorCodes::INVALID_CONFIGURATION,
                    'Product collection must contain arrays only.'
                );
            }

            $views[] = $this->projectProduct($product);
        }

        return $views;
    }
}
