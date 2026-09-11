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

        $limit = (int) ($criteria['limit'] ?? 50);
        $page = max(1, (int) ($criteria['page'] ?? 1));
        $query = [
            'taxonomy' => 'product_cat',
            'hide_empty' => ! array_key_exists('hide_empty', $criteria) || ! empty($criteria['hide_empty']),
            'menu_order' => 'ASC',
            'force_menu_order_sort' => true,
        ];

        if ($limit > 0) {
            $query['number'] = min(200, $limit);
            $query['offset'] = ($page - 1) * $query['number'];
        }

        $search = trim((string) ($criteria['search'] ?? ''));

        if ($search !== '') {
            $query['search'] = $search;
        }

        $terms = get_terms($query);

        if (is_wp_error($terms) || ! is_array($terms)) {
            return [];
        }

        $categories = [];

        foreach ($terms as $term) {
            if (! $term instanceof WP_Term) {
                continue;
            }

            $categories[] = [
                'id' => (int) $term->term_id,
                'slug' => (string) $term->slug,
                'name' => (string) $term->name,
                'sort_order' => (int) get_term_meta($term->term_id, 'order', true),
                'count' => (int) $term->count,
            ];
        }

        return $categories;
    }
}
