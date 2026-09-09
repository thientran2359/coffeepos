<?php

declare(strict_types=1);

namespace CoffeePOS\Infrastructure\Cart;

use CoffeePOS\Application\Contracts\SuspendedCartRepositoryInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;

final class WpdbSuspendedCartRepository implements SuspendedCartRepositoryInterface
{
    public function create(int $userId, string $label, array $cartPayload): array
    {
        global $wpdb;

        $encoded = wp_json_encode($cartPayload);
        if (! is_string($encoded) || $encoded === '') {
            throw Phase01Exception::withCode(Phase01ErrorCodes::HELD_CART_WRITE_FAILED, 'Held cart payload could not be encoded.');
        }

        $now = current_time('mysql', true);
        $inserted = $wpdb->insert($this->table(), [
            'user_id' => $userId,
            'label' => $label,
            'cart_payload' => $encoded,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%d', '%s', '%s', '%s', '%s']);

        if ($inserted !== 1) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::HELD_CART_WRITE_FAILED, 'Held cart could not be saved.');
        }

        $row = $this->findByIdForUser((int) $wpdb->insert_id, $userId);
        if ($row === null) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::HELD_CART_WRITE_FAILED, 'Saved held cart could not be loaded.');
        }

        return $row;
    }

    public function findByIdForUser(int $heldCartId, int $userId): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE id = %d AND user_id = %d LIMIT 1",
            $heldCartId,
            $userId
        ), ARRAY_A);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function listByUser(int $userId, int $limit): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE user_id = %d ORDER BY updated_at DESC, id DESC LIMIT %d",
            $userId,
            max(1, min(100, $limit))
        ), ARRAY_A);

        if (! is_array($rows)) {
            return [];
        }

        return array_map(function (array $row): array {
            return $this->hydrate($row);
        }, $rows);
    }

    public function deleteForUser(int $heldCartId, int $userId): void
    {
        global $wpdb;

        $deleted = $wpdb->delete($this->table(), ['id' => $heldCartId, 'user_id' => $userId], ['%d', '%d']);
        if ($deleted === false) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::HELD_CART_WRITE_FAILED, 'Held cart could not be deleted.');
        }
        if ($deleted === 0) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::HELD_CART_NOT_FOUND, 'Held cart was not found.');
        }
    }

    private function hydrate(array $row): array
    {
        $payload = json_decode((string) ($row['cart_payload'] ?? ''), true);
        if (! is_array($payload)) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_HELD_CART,
                'Stored held cart data is invalid.',
                ['held_cart_id' => (int) ($row['id'] ?? 0)]
            );
        }

        $row['id'] = (int) $row['id'];
        $row['user_id'] = (int) $row['user_id'];
        $row['cart_payload'] = $payload;

        return $row;
    }

    private function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'coffeepos_suspended_carts';
    }
}
