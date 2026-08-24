<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Cart;

use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Domain\Cart\Cart;
use CoffeePOS\Domain\Order\OrderType;
use CoffeePOS\Domain\Order\TableContext;

final class CartValidationService
{
    private bool $requireDineInTable;

    public function __construct(bool $requireDineInTable = true)
    {
        $this->requireDineInTable = $requireDineInTable;
    }

    public function validateQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_QUANTITY,
                'Quantity must be greater than or equal to one.'
            );
        }
    }

    public function validateOrderTypeAndTable(OrderType $orderType, TableContext $tableContext): void
    {
        if ($this->requireDineInTable && $orderType->isDineIn() && ! $tableContext->hasTable()) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::TABLE_REQUIRED,
                'Dine-in orders require table context.'
            );
        }

        if ($orderType->isTakeaway() && $tableContext->hasTable()) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::TABLE_NOT_ALLOWED,
                'Takeaway orders cannot retain table context.'
            );
        }
    }

    public function validateCart(Cart $cart): void
    {
        $this->validateOrderTypeAndTable($cart->orderType(), $cart->tableContext());

        foreach ($cart->items() as $item) {
            $this->validateQuantity($item->quantity());
        }
    }
}
