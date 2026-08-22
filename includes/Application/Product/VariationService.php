<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Product;

use CoffeePOS\Application\Contracts\VariationGatewayInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Projection\VariationView;

final class VariationService
{
    private ?VariationGatewayInterface $variationGateway;

    public function __construct(?VariationGatewayInterface $variationGateway = null)
    {
        $this->variationGateway = $variationGateway;
    }

    public function findById(int $variationId): VariationView
    {
        if ($variationId <= 0) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_VARIATION,
                'Variation id must be greater than zero.'
            );
        }

        if ($this->variationGateway === null) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CONFIGURATION,
                'Variation gateway is not configured.'
            );
        }

        $variation = $this->variationGateway->findById($variationId);

        if ($variation === null) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::VARIATION_NOT_FOUND,
                'Variation not found.',
                ['variation_id' => $variationId]
            );
        }

        return $this->projectVariation($variation);
    }

    public function resolveProductVariation(int $productId, array $selectedAttributes): VariationView
    {
        if ($this->variationGateway === null) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CONFIGURATION,
                'Variation gateway is not configured.'
            );
        }

        $variations = $this->variationGateway->findByProductId($productId);

        return $this->resolveVariation($productId, $selectedAttributes, $variations);
    }

    public function listByProductId(int $productId): array
    {
        if ($productId <= 0) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_PRODUCT,
                'Product id must be greater than zero.'
            );
        }

        if ($this->variationGateway === null) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CONFIGURATION,
                'Variation gateway is not configured.'
            );
        }

        $views = [];

        foreach ($this->variationGateway->findByProductId($productId) as $variation) {
            if (! is_array($variation) || (int) ($variation['product_id'] ?? 0) !== $productId) {
                continue;
            }

            $views[] = $this->projectVariation($variation);
        }

        return $views;
    }

    public function resolveVariation(int $productId, array $selectedAttributes, array $variations): VariationView
    {
        if ($productId <= 0) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_PRODUCT,
                'Product id must be greater than zero.'
            );
        }

        $normalizedSelection = $this->normalizeAttributes($selectedAttributes);

        if ($normalizedSelection === []) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_VARIATION,
                'Variation attribute selection is required.'
            );
        }

        $variationsForProduct = [];

        foreach ($variations as $variation) {
            if (! is_array($variation)) {
                continue;
            }

            if ((int) ($variation['product_id'] ?? 0) !== $productId) {
                continue;
            }

            $variationsForProduct[] = $variation;
        }

        if ($variationsForProduct === []) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::VARIATION_NOT_FOUND,
                'No variations found for product.',
                ['product_id' => $productId]
            );
        }

        $matchedVariation = null;

        foreach ($variationsForProduct as $variation) {
            $candidateAttributes = $this->normalizeAttributes(
                (array) ($variation['attributes'] ?? []),
                true
            );

            if (! $this->isExactAttributeSetMatch($normalizedSelection, $candidateAttributes)) {
                continue;
            }

            $matchedVariation = $variation;
            break;
        }

        if ($matchedVariation === null) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::VARIATION_NOT_FOUND,
                'Variation not found for selected attributes.',
                [
                    'product_id' => $productId,
                    'attributes' => $normalizedSelection,
                ]
            );
        }

        if (! (bool) ($matchedVariation['is_available'] ?? false)) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::OUT_OF_STOCK,
                'Selected variation is unavailable.',
                [
                    'product_id' => $productId,
                    'variation_id' => (int) ($matchedVariation['id'] ?? 0),
                ]
            );
        }

        return $this->projectVariation($matchedVariation);
    }

    public function projectVariation(array $variation): VariationView
    {
        $variationId = (int) ($variation['id'] ?? 0);
        $productId = (int) ($variation['product_id'] ?? 0);

        if ($variationId <= 0 || $productId <= 0) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_VARIATION,
                'Variation id and product id must be greater than zero.'
            );
        }

        $currency = strtoupper(trim((string) ($variation['currency'] ?? '')));

        if ($currency === '') {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CONFIGURATION,
                'Currency is required for variation projection.',
                ['variation_id' => $variationId]
            );
        }

        return new VariationView(
            $variationId,
            $productId,
            $this->normalizeAttributes((array) ($variation['attributes'] ?? [])),
            (int) ($variation['price_minor'] ?? 0),
            $currency,
            (bool) ($variation['is_available'] ?? false),
            (string) ($variation['price_amount'] ?? '0'),
            (string) ($variation['price_display'] ?? '')
        );
    }

    private function normalizeAttributes(array $attributes, bool $preserveEmptyValues = false): array
    {
        $normalized = [];

        foreach ($attributes as $key => $value) {
            $normalizedKey = strtolower(trim((string) $key));

            if (strpos($normalizedKey, 'attribute_') === 0) {
                $normalizedKey = substr($normalizedKey, strlen('attribute_'));
            }

            $normalizedValue = strtolower(trim((string) $value));

            if ($normalizedKey === '' || ($normalizedValue === '' && ! $preserveEmptyValues)) {
                continue;
            }

            $normalized[$normalizedKey] = $normalizedValue;
        }

        ksort($normalized, SORT_NATURAL | SORT_FLAG_CASE);

        return $normalized;
    }

    private function isExactAttributeSetMatch(array $selectedAttributes, array $candidateAttributes): bool
    {
        if (count($selectedAttributes) !== count($candidateAttributes)) {
            return false;
        }

        foreach ($candidateAttributes as $attributeName => $attributeValue) {
            if (! array_key_exists($attributeName, $selectedAttributes)) {
                return false;
            }

            // WooCommerce uses an empty variation attribute as an "Any" wildcard.
            if ($attributeValue !== '' && $selectedAttributes[$attributeName] !== $attributeValue) {
                return false;
            }
        }

        return true;
    }
}
