<?php

declare(strict_types=1);

namespace CoffeePOS\Infrastructure\Shift;

use CoffeePOS\Application\Contracts\ShiftRepositoryInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;

final class WpdbShiftRepository implements ShiftRepositoryInterface
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'coffeepos_shifts';
    }

    public function findOpenByUser(int $userId): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE user_id = %d AND status = 'open' ORDER BY id DESC LIMIT 1", $userId), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function findById(int $shiftId): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d LIMIT 1", $shiftId), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function create(int $userId, string $openingCash, string $openingNote): array
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $ok = $wpdb->insert($this->table(), [
            'user_id' => $userId, 'status' => 'open', 'opened_at' => $now,
            'opening_cash' => $openingCash, 'opening_note' => $openingNote,
            'created_at' => $now, 'updated_at' => $now,
        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s']);
        if ($ok !== 1) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::SHIFT_WRITE_FAILED, 'The shift could not be opened.');
        }
        return (array) $this->findById((int) $wpdb->insert_id);
    }

    public function close(int $shiftId, int $userId, string $actualCash, string $closingNote): array
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table()} SET status = 'closed', closed_at = %s, actual_cash = %s, closing_note = %s, updated_at = %s WHERE id = %d AND user_id = %d AND status = 'open'",
            $now, $actualCash, $closingNote, $now, $shiftId, $userId
        ));
        if ($updated !== 1) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::SHIFT_STATE_CONFLICT, 'The shift is no longer open.');
        }
        return (array) $this->findById($shiftId);
    }

    public function history(int $userId, int $limit): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE user_id = %d AND status = 'closed' ORDER BY closed_at DESC, id DESC LIMIT %d",
            $userId, $limit
        ), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }
}
