<?php

declare(strict_types=1);

namespace App\Domain\Project;

use InvalidArgumentException;

final class ProjectName
{
    private string $value;

    public function __construct(string $value)
    {
        $value = trim($value);
        if (empty($value)) {
            throw new InvalidArgumentException('ProjectName cannot be empty.');
        }
        if (mb_strlen($value) > 200) {
            throw new InvalidArgumentException('ProjectName must not exceed 200 characters.');
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
