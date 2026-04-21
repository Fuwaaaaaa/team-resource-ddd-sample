<?php

declare(strict_types=1);

namespace App\Application\Project\Commands;

final class UpsertRequiredSkillCommand
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $skillId,
        public readonly int $requiredProficiency,
        public readonly int $headcount,
    ) {}
}
