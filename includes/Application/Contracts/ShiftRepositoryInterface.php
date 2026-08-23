<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

interface ShiftRepositoryInterface
{
    public function findOpenByUser(int $userId): ?array;

    public function findById(int $shiftId): ?array;

    public function create(int $userId, string $openingCash, string $openingNote): array;

    public function close(int $shiftId, int $userId, string $actualCash, string $closingNote): array;

    public function history(int $userId, int $limit): array;
}
