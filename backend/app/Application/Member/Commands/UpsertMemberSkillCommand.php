<?php

declare(strict_types=1);

namespace App\Application\Member\Commands;

final class UpsertMemberSkillCommand
{
    public function __construct(
        public readonly string $memberId,
        public readonly string $skillId,
        public readonly int $proficiency,
    ) {
    }
}
