<?php

declare(strict_types=1);

namespace CoffeePOS\Infrastructure\Settings;

use CoffeePOS\Application\Contracts\TableProviderInterface;

final class SettingsTableProvider implements TableProviderInterface
{
    public function listAvailable(): array
    {
        $tables = array_values(array_filter(
            (array) Settings::get(Settings::OPTION_SERVICE_TABLES),
            static function ($table): bool {
                return is_array($table)
                    && ! empty($table['enabled'])
                    && (int) ($table['id'] ?? 0) > 0
                    && trim((string) ($table['label'] ?? '')) !== '';
            }
        ));

        $tables = array_map(static function (array $table): array {
            return [
                'id' => (int) $table['id'],
                'label' => sanitize_text_field((string) $table['label']),
                'enabled' => true,
                'sort_order' => (int) ($table['sort_order'] ?? 0),
            ];
        }, $tables);

        usort($tables, static function (array $left, array $right): int {
            $order = ((int) ($left['sort_order'] ?? 0)) <=> ((int) ($right['sort_order'] ?? 0));
            return $order !== 0 ? $order : (((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0)));
        });

        return $tables;
    }

    public function findAvailableById(int $tableId): ?array
    {
        foreach ($this->listAvailable() as $table) {
            if ((int) ($table['id'] ?? 0) === $tableId) {
                return $table;
            }
        }

        return null;
    }
}
