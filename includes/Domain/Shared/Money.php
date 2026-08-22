<?php

declare(strict_types=1);

namespace CoffeePOS\Domain\Shared;

final class Money
{
    private int $amountMinor;

    private string $currency;

    private function __construct(int $amountMinor, string $currency)
    {
        $this->amountMinor = $amountMinor;
        $this->currency = self::normalizeCurrency($currency);
    }

    public static function fromMinor(int $amountMinor, string $currency): self
    {
        return new self($amountMinor, $currency);
    }

    public static function zero(string $currency): self
    {
        return new self(0, $currency);
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function amountMinor(): int
    {
        return $this->amountMinor;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountMinor + $other->amountMinor, $this->currency);
    }

    public function multiply(int $multiplier): self
    {
        if ($multiplier < 0) {
            throw new \InvalidArgumentException('Money multiplier cannot be negative.');
        }

        return new self($this->amountMinor * $multiplier, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amountMinor === 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->amountMinor === $other->amountMinor;
    }

    public function toArray(): array
    {
        return [
            'amount_minor' => $this->amountMinor,
            'currency' => $this->currency,
        ];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException('Money currency mismatch.');
        }
    }

    private static function normalizeCurrency(string $currency): string
    {
        $normalized = strtoupper(trim($currency));

        if ($normalized === '') {
            throw new \InvalidArgumentException('Money currency cannot be empty.');
        }

        return $normalized;
    }
}
