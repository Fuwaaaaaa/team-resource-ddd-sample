<?php

declare(strict_types=1);

namespace App\Domain\Member;

use InvalidArgumentException;

final class MemberName
{
    private string $value;

    public function __construct(string $value)
    {
        $value = trim($value);
        if (empty($value)) {
            throw new InvalidArgumentException('MemberName cannot be empty.');
        }
        if (mb_strlen($value) > 100) {
            throw new InvalidArgumentException('MemberName must not exceed 100 characters.');
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
