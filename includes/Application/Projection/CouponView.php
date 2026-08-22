<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Projection;

final class CouponView
{
    private string $code;

    private bool $valid;

    private string $message;

    private function __construct(string $code, bool $valid, string $message)
    {
        $this->code = strtoupper(trim($code));
        $this->valid = $valid;
        $this->message = trim($message);
    }

    public static function valid(string $code, string $message = ''): self
    {
        return new self($code, true, $message);
    }

    public static function invalid(string $code, string $message = ''): self
    {
        return new self($code, false, $message);
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'is_valid' => $this->valid,
            'message' => $this->message,
        ];
    }
}
