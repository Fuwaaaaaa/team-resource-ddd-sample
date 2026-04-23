<?php

declare(strict_types=1);

namespace App\Application\Dashboard\DTOs;

final class ForecastBucketDto
{
    /** @param SkillForecastDto[] $skills */
    public function __construct(
        public readonly string $month, // YYYY-MM
        public readonly array $skills,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'month' => $this->month,
            'skills' => array_map(fn (SkillForecastDto $s): array => $s->toArray(), $this->skills),
        ];
    }
}
