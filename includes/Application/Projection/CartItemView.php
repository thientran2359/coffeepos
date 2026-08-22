<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Projection;

use CoffeePOS\Application\Contracts\MoneyFormatterInterface;
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

    private array $displaySnapshot;

    private string $unitPriceDisplay;

    private string $lineTotalDisplay;

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
        string $customNote,
        array $displaySnapshot,
        string $unitPriceDisplay,
        string $lineTotalDisplay
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
        $this->displaySnapshot = $displaySnapshot;
        $this->unitPriceDisplay = $unitPriceDisplay;
        $this->lineTotalDisplay = $lineTotalDisplay;
    }

    public static function fromDomain(CartItem $cartItem, ?MoneyFormatterInterface $formatter = null): self
    {
        $unitPriceDisplay = $formatter !== null
            ? $formatter->format($cartItem->unitPrice()->amountMinor(), $cartItem->unitPrice()->currency())
            : $cartItem->unitPrice()->amountMinor() . ' ' . $cartItem->unitPrice()->currency();
        $lineTotalDisplay = $formatter !== null
            ? $formatter->format($cartItem->lineTotal()->amountMinor(), $cartItem->lineTotal()->currency())
            : $cartItem->lineTotal()->amountMinor() . ' ' . $cartItem->lineTotal()->currency();

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
            $cartItem->customNote(),
            $cartItem->displaySnapshot(),
            $unitPriceDisplay,
            $lineTotalDisplay
        );
    }

    public function toArray(): array
    {
        $productName = (string) ($this->displaySnapshot['product_name'] ?? ('#' . $this->productId));
        $variationAttributes = (array) ($this->displaySnapshot['variation_attributes'] ?? []);
        $variationSummary = (string) ($this->displaySnapshot['variation_summary'] ?? '');
        $modifierLabels = array_values((array) ($this->displaySnapshot['modifier_labels'] ?? []));
        $quickNoteLabels = array_values((array) ($this->displaySnapshot['quick_note_labels'] ?? []));
        $noteParts = array_filter(array_merge($quickNoteLabels, [$this->customNote]));

        return [
            'item_id' => $this->itemId,
            'key' => $this->itemId,
            'product_id' => $this->productId,
            'product_name' => $productName,
            'product' => [
                'id' => $this->productId,
                'name' => $productName,
            ],
            'variation_id' => $this->variationId,
            'variation' => [
                'id' => $this->variationId,
                'attributes' => $variationAttributes,
            ],
            'variation_summary' => $variationSummary,
            'quantity' => $this->quantity,
            'unit_price_minor' => $this->unitPriceMinor,
            'unit_price_display' => $this->unitPriceDisplay,
            'unit_price' => [
                'amount_minor' => $this->unitPriceMinor,
                'display' => $this->unitPriceDisplay,
            ],
            'line_total_minor' => $this->lineTotalMinor,
            'line_total_display' => $this->lineTotalDisplay,
            'line_total' => [
                'amount_minor' => $this->lineTotalMinor,
                'display' => $this->lineTotalDisplay,
            ],
            'currency' => $this->currency,
            'modifiers' => $this->modifiers,
            'modifier_summary' => implode(' / ', $modifierLabels),
            'quick_notes' => $this->quickNotes,
            'quick_note_summary' => implode(', ', $quickNoteLabels),
            'custom_note' => $this->customNote,
            'note_display' => implode(' / ', $noteParts),
            'configuration' => [
                'modifiers' => $this->modifiers,
                'quick_notes' => $this->quickNotes,
                'custom_note' => $this->customNote,
            ],
        ];
    }
}
