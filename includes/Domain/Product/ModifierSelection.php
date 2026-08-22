<?php

declare(strict_types=1);

namespace CoffeePOS\Domain\Product;

final class ModifierSelection
{
    private array $groups;

    private function __construct(array $groups)
    {
        $this->groups = $groups;
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public static function fromArray(array $groups): self
    {
        $normalized = [];

        foreach ($groups as $groupKey => $values) {
            $normalizedGroupKey = self::normalizeGroupKey($groupKey);

            if ($normalizedGroupKey === '' || ! is_array($values)) {
                continue;
            }

            $normalizedValues = [];

            foreach ($values as $value) {
                $normalizedValue = trim((string) $value);

                if ($normalizedValue === '') {
                    continue;
                }

                $normalizedValues[] = $normalizedValue;
            }

            $normalizedValues = array_values(array_unique($normalizedValues));

            if ($normalizedValues === []) {
                continue;
            }

            sort($normalizedValues, SORT_NATURAL | SORT_FLAG_CASE);
            $normalized[$normalizedGroupKey] = $normalizedValues;
        }

        ksort($normalized, SORT_NATURAL | SORT_FLAG_CASE);

        return new self($normalized);
    }

    public function groups(): array
    {
        return $this->groups;
    }

    public function isEmpty(): bool
    {
        return $this->groups === [];
    }

    public function fingerprint(): string
    {
        return (string) json_encode($this->groups);
    }

    private static function normalizeGroupKey($groupKey): string
    {
        return strtolower(trim((string) $groupKey));
    }
}
