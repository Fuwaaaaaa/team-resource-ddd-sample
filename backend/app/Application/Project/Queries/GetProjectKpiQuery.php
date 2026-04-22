<?php

declare(strict_types=1);

namespace App\Application\Project\Queries;

final class GetProjectKpiQuery
{
    public function __construct(
        public string $projectId,
        public string $referenceDate, // Y-m-d
    ) {}
}
