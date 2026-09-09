<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Cart;

use CoffeePOS\Application\Contracts\CartSessionStoreInterface;
use CoffeePOS\Application\Contracts\CartSnapshotSerializerInterface;
use CoffeePOS\Application\Contracts\LockProviderInterface;
use CoffeePOS\Application\Contracts\SuspendedCartRepositoryInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Domain\Cart\Cart;

final class SuspendedCartService
{
    private SuspendedCartRepositoryInterface $repository;
    private CartSessionStoreInterface $sessionStore;
    private CartSnapshotSerializerInterface $serializer;
    private CartSessionService $cartSessions;
    private LockProviderInterface $locks;
    /** @var callable|null */
    private $dateFormatter;

    public function __construct(
        SuspendedCartRepositoryInterface $repository,
        CartSessionStoreInterface $sessionStore,
        CartSnapshotSerializerInterface $serializer,
        CartSessionService $cartSessions,
        LockProviderInterface $locks,
        ?callable $dateFormatter = null
    ) {
        $this->repository = $repository;
        $this->sessionStore = $sessionStore;
        $this->serializer = $serializer;
        $this->cartSessions = $cartSessions;
        $this->locks = $locks;
        $this->dateFormatter = $dateFormatter;
    }

    public function hold(int $userId, string $label, string $posSessionId, int $expectedRevision): array
    {
        $label = trim($label);
        $length = preg_match_all('/./us', $label, $characters);

        if ($length === false || $length < 1 || $length > 191) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_HELD_CART, 'Held cart label must contain 1 to 191 characters.');
        }

        return $this->locks->synchronized('held-cart-session-' . $posSessionId, function () use ($userId, $label, $posSessionId, $expectedRevision): array {
            $cart = $this->mutableCart($posSessionId, $expectedRevision);
            if (! $cart->hasItems()) {
                throw Phase01Exception::withCode(Phase01ErrorCodes::EMPTY_CART, 'An empty cart cannot be held.');
            }

            $row = $this->repository->create($userId, $label, $this->serializer->toPayload($cart));

            try {
                $resetCart = $this->cartSessions->resetAfterHold($posSessionId, $expectedRevision)->toArray();
            } catch (\Throwable $throwable) {
                try {
                    $this->repository->deleteForUser((int) $row['id'], $userId);
                } catch (\Throwable $rollbackFailure) {
                    // Keep the original failure. Diagnostics can inspect the orphaned snapshot.
                }
                throw $throwable;
            }

            return [
                'held_cart' => $this->project($row, false),
                'cart' => $resetCart,
            ];
        });
    }

    public function list(int $userId, int $limit = 100): array
    {
        return array_map(function (array $row): array {
            return $this->project($row, false);
        }, $this->repository->listByUser($userId, max(1, min(100, $limit))));
    }

    public function detail(int $heldCartId, int $userId): array
    {
        return $this->project($this->required($heldCartId, $userId), true);
    }

    public function resume(int $heldCartId, int $userId, string $posSessionId, int $expectedRevision): array
    {
        return $this->locks->synchronized('held-cart-' . $heldCartId, function () use ($heldCartId, $userId, $posSessionId, $expectedRevision): array {
            $row = $this->required($heldCartId, $userId);
            $cart = $this->cartSessions->resumeSuspended(
                $posSessionId,
                $expectedRevision,
                (array) $row['cart_payload']
            )->toArray();
            $this->repository->deleteForUser($heldCartId, $userId);

            return [
                'held_cart_id' => $heldCartId,
                'cart' => $cart,
            ];
        });
    }

    public function delete(int $heldCartId, int $userId): array
    {
        return $this->locks->synchronized('held-cart-' . $heldCartId, function () use ($heldCartId, $userId): array {
            $this->required($heldCartId, $userId);
            $this->repository->deleteForUser($heldCartId, $userId);

            return ['deleted' => true, 'held_cart_id' => $heldCartId];
        });
    }

    private function mutableCart(string $posSessionId, int $expectedRevision): Cart
    {
        $cart = $this->sessionStore->load($posSessionId);

        if ($cart === null) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::CART_SESSION_NOT_FOUND, 'Cart session was not found.');
        }

        if ($cart->state() !== Cart::STATE_ACTIVE) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_CART, 'Cart is frozen for checkout.');
        }

        if ($expectedRevision < 0 || $cart->revision() !== $expectedRevision) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::CART_REVISION_CONFLICT,
                'Cart revision is out of date.',
                [
                    'pos_session_id' => $posSessionId,
                    'expected_revision' => $expectedRevision,
                    'current_revision' => $cart->revision(),
                    'cart' => $this->cartSessions->getSession($posSessionId)->toArray(),
                ]
            );
        }

        return $cart;
    }

    private function required(int $heldCartId, int $userId): array
    {
        $row = $this->repository->findByIdForUser($heldCartId, $userId);

        if ($row === null) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::HELD_CART_NOT_FOUND, 'Held cart was not found.');
        }

        return $row;
    }

    private function project(array $row, bool $includeItems): array
    {
        $payload = (array) ($row['cart_payload'] ?? []);
        $items = (array) ($payload['items'] ?? []);
        $quantity = 0;
        $itemViews = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemQuantity = max(0, (int) ($item['quantity'] ?? 0));
            $quantity += $itemQuantity;

            if ($includeItems) {
                $snapshot = (array) ($item['display_snapshot'] ?? []);
                $itemViews[] = [
                    'name' => (string) ($snapshot['product_name'] ?? ''),
                    'variation_summary' => (string) ($snapshot['variation_summary'] ?? ''),
                    'quantity' => $itemQuantity,
                ];
            }
        }

        $createdAt = $this->isoDate((string) ($row['created_at'] ?? ''));
        $customer = (array) ($payload['customer'] ?? []);
        $table = (array) ($payload['table'] ?? []);
        $view = [
            'id' => (int) $row['id'],
            'label' => (string) $row['label'],
            'created_at' => $createdAt,
            'created_at_display' => $this->displayDate($createdAt),
            'item_count' => count($items),
            'total_quantity' => $quantity,
            'customer_label' => empty($customer['is_guest'])
                ? (string) ($customer['display_name'] ?? '')
                : __('Guest customer', 'coffeepos'),
            'order_type' => (string) ($payload['order_type'] ?? 'takeaway'),
            'table_label' => (string) ($table['table_label'] ?? ''),
        ];

        if ($includeItems) {
            $view['items'] = $itemViews;
            $view['order_note'] = (string) ($payload['order_note'] ?? '');
        }

        return $view;
    }

    private function isoDate(string $mysqlDate): string
    {
        if ($mysqlDate === '') {
            return '';
        }

        return function_exists('mysql2date') ? (string) mysql2date('c', $mysqlDate, false) : gmdate('c', (int) strtotime($mysqlDate));
    }

    private function displayDate(string $isoDate): string
    {
        $timestamp = strtotime($isoDate);
        if ($timestamp === false) {
            return '';
        }

        return $this->dateFormatter !== null
            ? (string) ($this->dateFormatter)($timestamp)
            : (function_exists('wp_date') ? wp_date('Y-m-d H:i', $timestamp) : gmdate('Y-m-d H:i', $timestamp));
    }
}
