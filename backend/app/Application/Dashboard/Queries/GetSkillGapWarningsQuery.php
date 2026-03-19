<?php

declare(strict_types=1);

namespace App\Application\Dashboard\Queries;

final class GetSkillGapWarningsQuery
{
    public function __construct(
        public readonly ?string $projectId,
        public readonly string $referenceDate,
    ) {
    }
}
