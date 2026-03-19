<?php

declare(strict_types=1);

namespace App\Domain\Allocation;

use InvalidArgumentException;

final class AllocationStatus
{
    private const ACTIVE = 'active';
    private const REVOKED = 'revoked';
    private const VALID_STATUSES = [self::ACTIVE, self::REVOKED];

    private string $value;

    private function __construct(string $value)
    {
        if (!in_array($value, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException("Invalid allocation status: {$value}.");
        }
        $this->value = $value;
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function revoked(): self
    {
        return new self(self::REVOKED);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function isActive(): bool
    {
        return $this->value === self::ACTIVE;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
