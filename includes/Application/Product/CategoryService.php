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

        $categories = [];

        foreach ($this->categoryGateway->search($criteria) as $category) {
            if (! is_array($category)) {
                continue;
            }

            $categoryId = (int) ($category['id'] ?? 0);
            $name = trim((string) ($category['name'] ?? ''));

            if ($categoryId <= 0 || $name === '') {
                continue;
            }

            $categories[] = [
                'id' => $categoryId,
                'slug' => trim((string) ($category['slug'] ?? '')),
                'name' => $name,
                'sort_order' => (int) ($category['sort_order'] ?? 0),
                'count' => max(0, (int) ($category['count'] ?? 0)),
            ];
        }

        usort($categories, static function (array $left, array $right): int {
            $order = $left['sort_order'] <=> $right['sort_order'];

            return $order !== 0 ? $order : ($left['id'] <=> $right['id']);
        });

        return $categories;
    }
}
