<?php

declare(strict_types=1);

namespace App\Domain\Project;

use InvalidArgumentException;

final class RequiredSkillId
{
    private string $value;

    public function __construct(string $value)
    {
        if (empty($value)) {
            throw new InvalidArgumentException('RequiredSkillId cannot be empty.');
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
