<?php

declare(strict_types=1);

namespace App\Application\Allocation\Queries;

final class SuggestAllocationCandidatesQuery
{
    public function __construct(
        public string $projectId,
        public string $skillId,
        public int $minimumProficiency,
        public string $periodStart, // Y-m-d
        public int $limit = 5,
    ) {}
}
