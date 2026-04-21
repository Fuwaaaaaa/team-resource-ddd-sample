<?php

declare(strict_types=1);

namespace App\Application\Member\DTOs;

final class SkillHistoryEntryDto
{
    public function __construct(
        public readonly string $skillId,
        public readonly int $proficiency,
        public readonly string $changedAt,   // ISO-8601
        public readonly ?string $changedBy,  // user id (null for system / seed)
        public readonly ?string $changedByName,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'skillId' => $this->skillId,
            'proficiency' => $this->proficiency,
            'changedAt' => $this->changedAt,
            'changedBy' => $this->changedBy,
            'changedByName' => $this->changedByName,
        ];
    }
}
