<?php

declare(strict_types=1);

namespace App\Application\Dashboard\DTOs;

final class SkillDto
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $category,
    ) {
    }
}
