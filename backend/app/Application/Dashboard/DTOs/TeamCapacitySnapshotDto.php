<?php

declare(strict_types=1);

namespace App\Application\Dashboard\DTOs;

final class TeamCapacitySnapshotDto
{
    /**
     * @param MemberCapacityEntryDto[] $entries
     * @param SkillDto[] $skills
     */
    public function __construct(
        public readonly array $entries,
        public readonly array $skills,
        public readonly string $referenceDate,
    ) {
    }
}
