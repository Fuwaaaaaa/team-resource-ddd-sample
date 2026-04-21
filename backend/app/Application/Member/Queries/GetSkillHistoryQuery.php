<?php

declare(strict_types=1);

namespace App\Application\Member\Queries;

final class GetSkillHistoryQuery
{
    public function __construct(
        public string $memberId,
        public ?string $skillId = null,       // 絞り込み
        public ?string $periodStart = null,   // ISO-8601 or null
        public ?string $periodEnd = null,
    ) {}
}
