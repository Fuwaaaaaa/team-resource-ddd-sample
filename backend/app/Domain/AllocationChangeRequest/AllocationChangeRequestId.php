<?php

declare(strict_types=1);

namespace App\Domain\AllocationChangeRequest;

use InvalidArgumentException;

final class AllocationChangeRequestId
{
    private string $value;

    public function __construct(string $value)
    {
        if ($value === '') {
            throw new InvalidArgumentException('AllocationChangeRequestId cannot be empty.');
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
