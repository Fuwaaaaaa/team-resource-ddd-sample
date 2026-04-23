<?php

declare(strict_types=1);

namespace App\Application\Dashboard\DTOs;

final class SkillForecastDto
{
    public function __construct(
        public readonly string $skillId,
        public readonly string $skillName,
        public readonly int $demandHeadcount,
        public readonly float $supplyHeadcountEquivalent,
        public readonly float $gap,
        public readonly string $severity, // ok | watch | critical
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'skillId' => $this->skillId,
            'skillName' => $this->skillName,
            'demandHeadcount' => $this->demandHeadcount,
            'supplyHeadcountEquivalent' => $this->supplyHeadcountEquivalent,
            'gap' => $this->gap,
            'severity' => $this->severity,
        ];
    }
}
