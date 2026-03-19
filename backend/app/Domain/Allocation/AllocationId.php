<?php

declare(strict_types=1);

namespace App\Domain\Allocation;

use InvalidArgumentException;

final class AllocationId
{
    private string $value;

    public function __construct(string $value)
    {
        if (empty($value)) {
            throw new InvalidArgumentException('AllocationId cannot be empty.');
        }
        $this->value = $value;
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
