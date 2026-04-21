<?php

declare(strict_types=1);

namespace App\Application\Project\Commands;

use App\Domain\Project\ProjectId;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentProjectRepository;

final class DeleteProjectHandler
{
    public function __construct(
        private EloquentProjectRepository $projectRepository,
    ) {
    }

    public function handle(string $projectId): void
    {
        $this->projectRepository->delete(new ProjectId($projectId));
    }
}
