<?php

declare(strict_types=1);

namespace App\Application\Dashboard\DTOs;

final class MemberCapacityEntryDto
{
    /**
     * @param array<string, int|null> $skillProficiencies skillId => proficiency(1-5) | null
     */
    public function __construct(
        public readonly string $memberId,
        public readonly string $memberName,
        public readonly int $availablePercentage,
        public readonly array $skillProficiencies,
    ) {
    }
}
