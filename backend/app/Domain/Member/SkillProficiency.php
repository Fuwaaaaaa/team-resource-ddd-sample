<?php

declare(strict_types=1);

namespace App\Domain\Member;

use App\Domain\Project\RequiredProficiency;
use InvalidArgumentException;

final class SkillProficiency
{
    private const MIN = 1;
    private const MAX = 5;

    private int $level;

    public function __construct(int $level)
    {
        if ($level < self::MIN || $level > self::MAX) {
            throw new InvalidArgumentException(
                "Skill proficiency must be between " . self::MIN . " and " . self::MAX . ", got {$level}."
            );
        }
        $this->level = $level;
    }

    public function level(): int
    {
        return $this->level;
    }

    /** この熟練度が要求水準を満たすか */
    public function satisfies(RequiredProficiency $required): bool
    {
        return $this->level >= $required->level();
    }

    /** 要求水準とのギャップ（正=余剰、負=不足） */
    public function gapAgainst(RequiredProficiency $required): int
    {
        return $this->level - $required->level();
    }

    public function equals(self $other): bool
    {
        return $this->level === $other->level;
    }
}
