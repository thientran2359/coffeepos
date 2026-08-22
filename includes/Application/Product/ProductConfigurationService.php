<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Product;

use CoffeePOS\Application\Contracts\ProductConfigurationProviderInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;

final class ProductConfigurationService
{
    private ProductService $productService;

    private VariationService $variationService;

    private ProductConfigurationProviderInterface $configurationProvider;

    public function __construct(
        ProductService $productService,
        VariationService $variationService,
        ProductConfigurationProviderInterface $configurationProvider
    ) {
        $this->productService = $productService;
        $this->variationService = $variationService;
        $this->configurationProvider = $configurationProvider;
    }

    public function detail(int $productId): array
    {
        $product = $this->productService->getById($productId)->toArray();
        $variations = [];

        if ((bool) ($product['is_variable'] ?? false)) {
            foreach ($this->variationService->listByProductId($productId) as $variationView) {
                $variations[] = $variationView->toArray();
            }
        }

        $configuration = $this->configurationProvider->configurationForProduct($product);

        return [
            'product' => $product,
            'attributes' => array_values((array) ($product['attributes'] ?? [])),
            'variations' => $variations,
            'modifier_groups' => array_values((array) ($configuration['modifier_groups'] ?? [])),
            'quick_notes' => array_values((array) ($configuration['quick_notes'] ?? [])),
        ];
    }

    public function validateSelections(array $product, array $modifiers, array $quickNotes): array
    {
        $configuration = $this->configurationProvider->configurationForProduct($product);
        $groups = array_values((array) ($configuration['modifier_groups'] ?? []));
        $notes = array_values((array) ($configuration['quick_notes'] ?? []));
        $groupById = [];

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $groupId = trim((string) ($group['id'] ?? ''));

            if ($groupId !== '') {
                $groupById[$groupId] = $group;
            }
        }

        foreach ($modifiers as $groupId => $optionIds) {
            if (! array_key_exists((string) $groupId, $groupById) || ! is_array($optionIds)) {
                throw Phase01Exception::withCode(
                    Phase01ErrorCodes::INVALID_CONFIGURATION,
                    'Modifier selection contains an unknown group.',
                    ['group_id' => (string) $groupId]
                );
            }
        }

        $normalizedModifiers = [];
        $modifierLabels = [];

        foreach ($groupById as $groupId => $group) {
            $selectedIds = array_values(array_unique(array_filter(array_map('strval', (array) ($modifiers[$groupId] ?? [])))));
            $optionById = [];

            foreach ((array) ($group['options'] ?? []) as $option) {
                if (! is_array($option)) {
                    continue;
                }

                $optionId = trim((string) ($option['id'] ?? ''));

                if ($optionId !== '') {
                    $optionById[$optionId] = (string) ($option['label'] ?? $optionId);
                }
            }

            foreach ($selectedIds as $selectedId) {
                if (! array_key_exists($selectedId, $optionById)) {
                    throw Phase01Exception::withCode(
                        Phase01ErrorCodes::INVALID_CONFIGURATION,
                        'Modifier selection contains an unknown option.',
                        ['group_id' => $groupId, 'option_id' => $selectedId]
                    );
                }
            }

            $selection = (string) ($group['selection'] ?? 'single');
            $minimum = max(0, (int) ($group['minimum'] ?? (! empty($group['required']) ? 1 : 0)));
            $maximumDefault = $selection === 'single' ? 1 : count($optionById);
            $maximum = max($minimum, (int) ($group['maximum'] ?? $maximumDefault));
            $count = count($selectedIds);

            if (($selection === 'single' && $count > 1) || $count < $minimum || $count > $maximum) {
                throw Phase01Exception::withCode(
                    Phase01ErrorCodes::INVALID_CONFIGURATION,
                    'Modifier selection does not satisfy its group rules.',
                    [
                        'group_id' => $groupId,
                        'minimum' => $minimum,
                        'maximum' => $maximum,
                        'selected' => $count,
                    ]
                );
            }

            if ($selectedIds === []) {
                continue;
            }

            sort($selectedIds, SORT_NATURAL | SORT_FLAG_CASE);
            $normalizedModifiers[$groupId] = $selectedIds;
            $selectedLabels = array_map(static function (string $optionId) use ($optionById): string {
                return $optionById[$optionId];
            }, $selectedIds);
            $modifierLabels[] = (string) ($group['label'] ?? $groupId) . ': ' . implode(', ', $selectedLabels);
        }

        $noteById = [];

        foreach ($notes as $note) {
            if (! is_array($note)) {
                continue;
            }

            $noteId = trim((string) ($note['id'] ?? ''));

            if ($noteId !== '') {
                $noteById[$noteId] = (string) ($note['label'] ?? $noteId);
            }
        }

        $normalizedNotes = array_values(array_unique(array_filter(array_map('strval', $quickNotes))));
        $quickNoteLabels = [];

        foreach ($normalizedNotes as $noteId) {
            if (! array_key_exists($noteId, $noteById)) {
                throw Phase01Exception::withCode(
                    Phase01ErrorCodes::INVALID_CONFIGURATION,
                    'Quick note selection contains an unknown note.',
                    ['quick_note_id' => $noteId]
                );
            }

            $quickNoteLabels[] = $noteById[$noteId];
        }

        sort($normalizedNotes, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'modifiers' => $normalizedModifiers,
            'modifier_labels' => $modifierLabels,
            'quick_notes' => $normalizedNotes,
            'quick_note_labels' => $quickNoteLabels,
        ];
    }
}
