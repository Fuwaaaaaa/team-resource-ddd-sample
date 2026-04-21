<?php

declare(strict_types=1);

namespace App\Domain\Availability;

use InvalidArgumentException;

final class AbsenceId
{
    private string $value;

    public function __construct(string $value)
    {
        if ($value === '') {
            throw new InvalidArgumentException('AbsenceId must not be empty.');
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
