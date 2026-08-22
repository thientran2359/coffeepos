<?php

declare(strict_types=1);

namespace CoffeePOS\Infrastructure\Settings;

use CoffeePOS\Application\Contracts\ProductConfigurationProviderInterface;

final class SettingsProductConfigurationProvider implements ProductConfigurationProviderInterface
{
    public function configurationForProduct(array $product): array
    {
        $productId = (int) ($product['id'] ?? 0);
        $categoryIds = array_map('intval', (array) ($product['category_ids'] ?? []));
        $modifierGroups = $this->filterApplicable(
            (array) Settings::get(Settings::OPTION_MODIFIER_GROUPS),
            $productId,
            $categoryIds
        );
        $quickNotes = $this->filterApplicable(
            (array) Settings::get(Settings::OPTION_QUICK_NOTES),
            $productId,
            $categoryIds
        );

        foreach ($modifierGroups as &$group) {
            $minimum = max(0, (int) ($group['minimum'] ?? 0));
            $maximum = max($minimum, (int) ($group['maximum'] ?? 1));
            $group['rule_label'] = $this->ruleLabel(
                (string) ($group['selection'] ?? 'single'),
                $minimum,
                $maximum
            );
        }
        unset($group);

        return [
            'modifier_groups' => $modifierGroups,
            'quick_notes' => $quickNotes,
        ];
    }

    private function filterApplicable(array $definitions, int $productId, array $categoryIds): array
    {
        $applicable = [];

        foreach ($definitions as $definition) {
            if (! is_array($definition) || empty($definition['enabled'])) {
                continue;
            }

            $productIds = array_map('intval', (array) ($definition['product_ids'] ?? []));
            $definitionCategoryIds = array_map('intval', (array) ($definition['category_ids'] ?? []));

            if ($productIds !== [] && ! in_array($productId, $productIds, true)) {
                continue;
            }

            if ($definitionCategoryIds !== [] && array_intersect($definitionCategoryIds, $categoryIds) === []) {
                continue;
            }

            $applicable[] = $definition;
        }

        usort($applicable, static function (array $left, array $right): int {
            $order = ((int) ($left['sort_order'] ?? 0)) <=> ((int) ($right['sort_order'] ?? 0));

            return $order !== 0
                ? $order
                : strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? ''));
        });

        return $applicable;
    }

    private function ruleLabel(string $selection, int $minimum, int $maximum): string
    {
        if ($selection === 'single') {
            return $minimum > 0
                ? __('Choose one', 'coffeepos')
                : __('Choose up to one', 'coffeepos');
        }

        if ($minimum > 0) {
            return sprintf(
                /* translators: 1: minimum selections, 2: maximum selections. */
                __('Choose %1$d to %2$d', 'coffeepos'),
                $minimum,
                $maximum
            );
        }

        return sprintf(
            /* translators: %d: maximum selections. */
            __('Choose up to %d', 'coffeepos'),
            $maximum
        );
    }
}
