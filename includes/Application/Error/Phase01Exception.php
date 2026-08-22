<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Error;

use RuntimeException;

final class Phase01Exception extends RuntimeException
{
    private string $errorCode;

    private array $context;

    public function __construct(string $errorCode, string $message, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
        $this->context = $context;
    }

    public static function withCode(string $errorCode, string $message, array $context = []): self
    {
        return new self($errorCode, $message, $context);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function context(): array
    {
        return $this->context;
    }
}
