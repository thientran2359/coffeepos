<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Product;

use CoffeePOS\Application\Contracts\CategoryGatewayInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;

final class CategoryService
{
    private ?CategoryGatewayInterface $categoryGateway;

    public function __construct(?CategoryGatewayInterface $categoryGateway = null)
    {
        $this->categoryGateway = $categoryGateway;
    }

    public function search(array $criteria = []): array
    {
        if ($this->categoryGateway === null) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CONFIGURATION,
                'Category gateway is not configured.'
            );
        }

        return $this->projectCategories($this->categoryGateway->search($criteria));
    }

    public function projectCategories(array $categories): array
    {
        $result = [];

        foreach ($categories as $category) {
            if (! is_array($category)) {
                throw Phase01Exception::withCode(
                    Phase01ErrorCodes::INVALID_CONFIGURATION,
                    'Category collection must contain arrays only.'
                );
            }

            $categoryId = (int) ($category['id'] ?? 0);
            $name = trim((string) ($category['name'] ?? ''));

            if ($categoryId <= 0 || $name === '') {
                continue;
            }

            $result[] = [
                'id' => $categoryId,
                'name' => $name,
                'slug' => sanitize_title((string) ($category['slug'] ?? $name)),
                'count' => max(0, (int) ($category['count'] ?? 0)),
            ];
        }

        return $result;
    }
}
