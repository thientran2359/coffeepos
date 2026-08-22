<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

use CoffeePOS\Application\Contracts\CategoryGatewayInterface;
use WP_Term;

final class WooCommerceCategoryGateway implements CategoryGatewayInterface
{
    public function search(array $criteria = []): array
    {
        if (! function_exists('get_terms')) {
            return [];
        }

        $limit = max(1, min(100, (int) ($criteria['limit'] ?? 50)));
        $page = max(1, (int) ($criteria['page'] ?? 1));
        $search = trim((string) ($criteria['search'] ?? ''));

        $query = [
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
            'number' => $limit,
            'offset' => ($page - 1) * $limit,
        ];

        if ($search !== '') {
            $query['search'] = $search;
        }

        $terms = get_terms($query);

        if (is_wp_error($terms) || ! is_array($terms)) {
            return [];
        }

        $result = [];

        foreach ($terms as $term) {
            if (! $term instanceof WP_Term) {
                continue;
            }

            $result[] = [
                'id' => (int) $term->term_id,
                'name' => (string) $term->name,
                'slug' => (string) $term->slug,
                'count' => (int) $term->count,
            ];
        }

        return $result;
    }
}
