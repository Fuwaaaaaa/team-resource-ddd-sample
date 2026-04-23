<?php

declare(strict_types=1);

namespace App\Application\Project\Commands;

final class UpdateProjectCommand
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $name,
        public readonly ?string $plannedStartDate = null,
        public readonly ?string $plannedEndDate = null,
    ) {}
}
