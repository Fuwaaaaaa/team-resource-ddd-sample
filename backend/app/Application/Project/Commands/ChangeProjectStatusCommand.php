<?php

declare(strict_types=1);

namespace App\Application\Project\Commands;

final class ChangeProjectStatusCommand
{
    public function __construct(
        public string $projectId,
        public string $status, // ProjectStatus value
    ) {}
}
