<?php

declare(strict_types=1);

namespace App\Application\Dashboard\DTOs;

final class SkillGapWarningDto
{
    public function __construct(
        public readonly string $memberId,
        public readonly string $memberName,
        public readonly string $projectId,
        public readonly string $projectName,
        public readonly string $skillId,
        public readonly string $skillName,
        public readonly int $requiredLevel,
        public readonly ?int $actualLevel,
        public readonly int $deficitLevel,
    ) {}
}
