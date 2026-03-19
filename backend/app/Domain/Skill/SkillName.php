<?php

declare(strict_types=1);

namespace App\Domain\Skill;

use InvalidArgumentException;

final class SkillName
{
    private string $value;

    public function __construct(string $value)
    {
        $value = trim($value);
        if (empty($value)) {
            throw new InvalidArgumentException('SkillName cannot be empty.');
        }
        if (mb_strlen($value) > 100) {
            throw new InvalidArgumentException('SkillName must not exceed 100 characters.');
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
