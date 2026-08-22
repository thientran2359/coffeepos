<?php

declare(strict_types=1);

namespace CoffeePOS\Domain\Cart;

use CoffeePOS\Domain\Product\ModifierSelection;
use CoffeePOS\Domain\Product\QuickNoteSelection;
use CoffeePOS\Domain\Shared\Money;

final class CartItem
{
    private string $identity;

    private int $productId;

    private int $variationId;

    private int $quantity;

    private Money $unitPrice;

    private ModifierSelection $modifierSelection;

    private QuickNoteSelection $quickNoteSelection;

    private string $customNote;

    private array $displaySnapshot;

    private function __construct(
        int $productId,
        int $variationId,
        int $quantity,
        Money $unitPrice,
        ModifierSelection $modifierSelection,
        QuickNoteSelection $quickNoteSelection,
        string $customNote,
        array $displaySnapshot
    ) {
        if ($productId <= 0) {
            throw new \InvalidArgumentException('Product id must be greater than zero.');
        }

        if ($variationId < 0) {
            throw new \InvalidArgumentException('Variation id cannot be negative.');
        }

        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be greater than or equal to one.');
        }

        $this->productId = $productId;
        $this->variationId = $variationId;
        $this->quantity = $quantity;
        $this->unitPrice = $unitPrice;
        $this->modifierSelection = $modifierSelection;
        $this->quickNoteSelection = $quickNoteSelection;
        $this->customNote = trim($customNote);
        $this->displaySnapshot = $displaySnapshot;
        $this->identity = self::buildIdentity(
            $this->productId,
            $this->variationId,
            $this->modifierSelection,
            $this->quickNoteSelection,
            $this->customNote
        );
    }

    public static function create(
        int $productId,
        int $variationId,
        int $quantity,
        Money $unitPrice,
        ?ModifierSelection $modifierSelection = null,
        ?QuickNoteSelection $quickNoteSelection = null,
        string $customNote = '',
        array $displaySnapshot = []
    ): self {
        return new self(
            $productId,
            $variationId,
            $quantity,
            $unitPrice,
            $modifierSelection ?? ModifierSelection::empty(),
            $quickNoteSelection ?? QuickNoteSelection::empty(),
            $customNote,
            $displaySnapshot
        );
    }

    public function identity(): string
    {
        return $this->identity;
    }

    public function productId(): int
    {
        return $this->productId;
    }

    public function variationId(): int
    {
        return $this->variationId;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function unitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function modifierSelection(): ModifierSelection
    {
        return $this->modifierSelection;
    }

    public function quickNoteSelection(): QuickNoteSelection
    {
        return $this->quickNoteSelection;
    }

    public function customNote(): string
    {
        return $this->customNote;
    }

    public function displaySnapshot(): array
    {
        return $this->displaySnapshot;
    }

    public function lineTotal(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }

    public function withQuantity(int $quantity): self
    {
        return new self(
            $this->productId,
            $this->variationId,
            $quantity,
            $this->unitPrice,
            $this->modifierSelection,
            $this->quickNoteSelection,
            $this->customNote,
            $this->displaySnapshot
        );
    }

    public function withAddedQuantity(int $quantity): self
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Additional quantity must be greater than or equal to one.');
        }

        return $this->withQuantity($this->quantity + $quantity);
    }

    public function canMergeWith(self $other): bool
    {
        return $this->identity === $other->identity;
    }

    public function merge(self $other): self
    {
        if (! $this->canMergeWith($other)) {
            throw new \InvalidArgumentException('Cannot merge cart items with different identities.');
        }

        return $this->withAddedQuantity($other->quantity);
    }

    private static function buildIdentity(
        int $productId,
        int $variationId,
        ModifierSelection $modifierSelection,
        QuickNoteSelection $quickNoteSelection,
        string $customNote
    ): string {
        $payload = [
            'product_id' => $productId,
            'variation_id' => $variationId,
            'modifiers' => $modifierSelection->groups(),
            'quick_notes' => $quickNoteSelection->notes(),
            'custom_note' => $customNote,
        ];

        return hash('sha256', (string) json_encode($payload));
    }
}
