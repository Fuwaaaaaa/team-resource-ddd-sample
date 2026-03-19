<?php

declare(strict_types=1);

namespace App\Domain\Allocation;

use InvalidArgumentException;

final class AllocationPercentage
{
    private int $value;

    public function __construct(int $value)
    {
        if ($value < 0 || $value > 100) {
            throw new InvalidArgumentException(
                "Allocation percentage must be between 0 and 100, got {$value}."
            );
        }
        $this->value = $value;
    }

    public function value(): int
    {
        return $this->value;
    }

    public function add(self $other): self
    {
        return new self($this->value + $other->value);
    }

    public function exceeds(int $limit): bool
    {
        return $this->value > $limit;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
