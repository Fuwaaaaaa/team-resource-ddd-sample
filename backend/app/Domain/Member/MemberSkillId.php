<?php

declare(strict_types=1);

namespace App\Domain\Member;

use InvalidArgumentException;

final class MemberSkillId
{
    private string $value;

    public function __construct(string $value)
    {
        if (empty($value)) {
            throw new InvalidArgumentException('MemberSkillId cannot be empty.');
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
