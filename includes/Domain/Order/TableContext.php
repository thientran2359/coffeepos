<?php

declare(strict_types=1);

namespace CoffeePOS\Domain\Order;

final class TableContext
{
    private ?int $tableId;

    private string $tableLabel;

    private function __construct(?int $tableId, string $tableLabel)
    {
        $this->tableId = $tableId;
        $this->tableLabel = $tableLabel;
    }

    public static function none(): self
    {
        return new self(null, '');
    }

    public static function from(?int $tableId, string $tableLabel = ''): self
    {
        if ($tableId === null || $tableId <= 0) {
            return self::none();
        }

        return new self($tableId, trim($tableLabel));
    }

    public function hasTable(): bool
    {
        return $this->tableId !== null;
    }

    public function tableId(): ?int
    {
        return $this->tableId;
    }

    public function tableLabel(): string
    {
        return $this->tableLabel;
    }

    public function toArray(): array
    {
        return [
            'table_id' => $this->tableId,
            'table_label' => $this->tableLabel,
        ];
    }
}
