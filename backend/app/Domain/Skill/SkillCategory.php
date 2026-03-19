<?php

declare(strict_types=1);

namespace App\Domain\Skill;

use InvalidArgumentException;

final class SkillCategory
{
    private const VALID_CATEGORIES = [
        'programming_language',
        'framework',
        'infrastructure',
        'database',
        'design',
        'management',
        'other',
    ];

    private string $value;

    public function __construct(string $value)
    {
        if (!in_array($value, self::VALID_CATEGORIES, true)) {
            throw new InvalidArgumentException(
                "Invalid skill category: {$value}. Valid categories: " . implode(', ', self::VALID_CATEGORIES)
            );
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

    /** @return string[] */
    public static function validCategories(): array
    {
        return self::VALID_CATEGORIES;
    }
}
