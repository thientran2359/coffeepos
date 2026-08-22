<?php

declare(strict_types=1);

namespace CoffeePOS\Domain\Product;

final class QuickNoteSelection
{
    private array $notes;

    private function __construct(array $notes)
    {
        $this->notes = $notes;
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public static function fromArray(array $notes): self
    {
        $normalized = [];

        foreach ($notes as $note) {
            $normalizedNote = trim((string) $note);

            if ($normalizedNote === '') {
                continue;
            }

            $normalized[] = $normalizedNote;
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized, SORT_NATURAL | SORT_FLAG_CASE);

        return new self($normalized);
    }

    public function notes(): array
    {
        return $this->notes;
    }

    public function isEmpty(): bool
    {
        return $this->notes === [];
    }

    public function fingerprint(): string
    {
        return (string) json_encode($this->notes);
    }
}
