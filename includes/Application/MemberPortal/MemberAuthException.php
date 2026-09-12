<?php

declare(strict_types=1);

namespace CoffeePOS\Application\MemberPortal;

final class MemberAuthException extends \RuntimeException
{
    private string $errorCode;

    private int $status;

    private array $details;

    public function __construct(string $errorCode, string $message, int $status, array $details = [])
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->status = $status;
        $this->details = $details;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function details(): array
    {
        return $this->details;
    }
}
