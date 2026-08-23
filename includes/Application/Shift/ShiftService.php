<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Shift;

use CoffeePOS\Application\Contracts\LockProviderInterface;
use CoffeePOS\Application\Contracts\ShiftRepositoryInterface;
use CoffeePOS\Application\Contracts\ShiftTotalsGatewayInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;

final class ShiftService
{
    private ShiftRepositoryInterface $repository;
    private ShiftTotalsGatewayInterface $totals;
    private LockProviderInterface $locks;

    public function __construct(ShiftRepositoryInterface $repository, ShiftTotalsGatewayInterface $totals, LockProviderInterface $locks)
    {
        $this->repository = $repository;
        $this->totals = $totals;
        $this->locks = $locks;
    }

    public function current(int $userId): ?array
    {
        $row = $this->repository->findOpenByUser($userId);
        return $row ? $this->project($row) : null;
    }

    public function requireOpen(int $userId): array
    {
        $shift = $this->current($userId);
        if ($shift === null) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::SHIFT_REQUIRED, 'Open a shift before completing checkout.');
        }
        return $shift;
    }

    public function open(int $userId, string $openingCash, string $openingNote): array
    {
        $money = $this->money($openingCash, 'opening cash');
        return $this->locks->synchronized('shift-user-' . $userId, function () use ($userId, $money, $openingNote): array {
            if ($this->repository->findOpenByUser($userId)) {
                throw Phase01Exception::withCode(Phase01ErrorCodes::SHIFT_ALREADY_OPEN, 'This cashier already has an open shift.');
            }
            return $this->project($this->repository->create($userId, $money, $this->note($openingNote)));
        });
    }

    public function close(int $shiftId, int $userId, string $actualCash, string $closingNote): array
    {
        $money = $this->money($actualCash, 'actual cash');
        return $this->locks->synchronized('shift-user-' . $userId, function () use ($shiftId, $userId, $money, $closingNote): array {
            $row = $this->repository->findById($shiftId);
            if (! $row || (int) $row['user_id'] !== $userId) {
                throw Phase01Exception::withCode(Phase01ErrorCodes::SHIFT_NOT_FOUND, 'Shift was not found.');
            }
            return $this->project($this->repository->close($shiftId, $userId, $money, $this->note($closingNote)));
        });
    }

    public function history(int $userId, int $limit = 50): array
    {
        return array_map(function (array $row): array { return $this->project($row); }, $this->repository->history($userId, max(1, min(100, $limit))));
    }

    private function project(array $row): array
    {
        $derived = $this->totals->totals((int) $row['id']);
        $opening = (float) $row['opening_cash'];
        $expected = $opening + (float) $derived['cash_sales'];
        $actual = $row['actual_cash'] === null ? null : (float) $row['actual_cash'];
        return [
            'id' => (int) $row['id'], 'user_id' => (int) $row['user_id'], 'status' => (string) $row['status'],
            'cashier_name' => (string) get_the_author_meta('display_name', (int) $row['user_id']),
            'opened_at' => mysql2date('c', (string) $row['opened_at'], false), 'closed_at' => $row['closed_at'] ? mysql2date('c', (string) $row['closed_at'], false) : null,
            'opening_cash' => $this->decimal($opening), 'opening_note' => (string) ($row['opening_note'] ?? ''),
            'cash_sales' => $this->decimal((float) $derived['cash_sales']), 'bank_sales' => $this->decimal((float) $derived['bank_sales']),
            'total_sales' => $this->decimal((float) $derived['total_sales']), 'order_count' => (int) $derived['order_count'],
            'expected_cash' => $this->decimal($expected), 'actual_cash' => $actual === null ? null : $this->decimal($actual),
            'variance' => $actual === null ? null : $this->decimal($actual - $expected), 'closing_note' => (string) ($row['closing_note'] ?? ''),
            'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '',
        ];
    }

    private function money(string $value, string $label): string
    {
        $value = trim($value);
        if (! preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]{1,6}))?$/', $value)) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_SHIFT_AMOUNT, 'Invalid ' . $label . '.');
        }
        return $this->decimal((float) $value);
    }

    private function decimal(float $value): string
    {
        $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;
        return number_format($value, $decimals, '.', '');
    }

    private function note(string $value): string
    {
        $value = sanitize_textarea_field($value);
        if (strlen($value) > 2000) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_SHIFT_NOTE, 'Shift note is too long.');
        }
        return $value;
    }
}
