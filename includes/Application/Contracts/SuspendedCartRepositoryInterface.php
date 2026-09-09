<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

interface SuspendedCartRepositoryInterface
{
    public function create(int $userId, string $label, array $cartPayload): array;

    public function findByIdForUser(int $heldCartId, int $userId): ?array;

    public function listByUser(int $userId, int $limit): array;

    public function deleteForUser(int $heldCartId, int $userId): void;
}
