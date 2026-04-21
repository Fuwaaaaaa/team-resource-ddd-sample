<?php

declare(strict_types=1);

namespace App\Domain\Project;

use InvalidArgumentException;

final class RequiredProficiency
{
    private const MIN = 1;

    private const MAX = 5;

    private int $level;

    public function __construct(int $level)
    {
        if ($level < self::MIN || $level > self::MAX) {
            throw new InvalidArgumentException(
                'Required proficiency must be between '.self::MIN.' and '.self::MAX.", got {$level}."
            );
        }
        $this->level = $level;
    }

    public function level(): int
    {
        return $this->level;
    }

    public function equals(self $other): bool
    {
        return $this->level === $other->level;
    }
}
