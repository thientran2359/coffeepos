<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Projection;

use CoffeePOS\Domain\Cart\CartItem;

final class CartItemView
{
    private string $itemId;

    private int $productId;

    private int $variationId;

    private int $quantity;

    private int $unitPriceMinor;

    private int $lineTotalMinor;

    private string $currency;

    private array $modifiers;

    private array $quickNotes;

    private string $customNote;

    private function __construct(
        string $itemId,
        int $productId,
        int $variationId,
        int $quantity,
        int $unitPriceMinor,
        int $lineTotalMinor,
        string $currency,
        array $modifiers,
        array $quickNotes,
        string $customNote
    ) {
        $this->itemId = $itemId;
        $this->productId = $productId;
        $this->variationId = $variationId;
        $this->quantity = $quantity;
        $this->unitPriceMinor = $unitPriceMinor;
        $this->lineTotalMinor = $lineTotalMinor;
        $this->currency = $currency;
        $this->modifiers = $modifiers;
        $this->quickNotes = $quickNotes;
        $this->customNote = $customNote;
    }

    public static function fromDomain(CartItem $cartItem): self
    {
        return new self(
            $cartItem->identity(),
            $cartItem->productId(),
            $cartItem->variationId(),
            $cartItem->quantity(),
            $cartItem->unitPrice()->amountMinor(),
            $cartItem->lineTotal()->amountMinor(),
            $cartItem->unitPrice()->currency(),
            $cartItem->modifierSelection()->groups(),
            $cartItem->quickNoteSelection()->notes(),
            $cartItem->customNote()
        );
    }

    public function toArray(): array
    {
        return [
            'item_id' => $this->itemId,
            'product_id' => $this->productId,
            'variation_id' => $this->variationId,
            'quantity' => $this->quantity,
            'unit_price_minor' => $this->unitPriceMinor,
            'line_total_minor' => $this->lineTotalMinor,
            'currency' => $this->currency,
            'modifiers' => $this->modifiers,
            'quick_notes' => $this->quickNotes,
            'custom_note' => $this->customNote,
        ];
    }
}
