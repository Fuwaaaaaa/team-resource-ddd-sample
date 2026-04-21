<?php

declare(strict_types=1);

namespace App\Application\Project\Commands;

use App\Application\Project\DTOs\ProjectDto;
use App\Domain\Project\ProjectId;
use App\Domain\Project\ProjectName;
use App\Domain\Project\ProjectRepositoryInterface;
use ReflectionClass;
use RuntimeException;

final class UpdateProjectHandler
{
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
    ) {}

    public function handle(UpdateProjectCommand $command): ProjectDto
    {
        $project = $this->projectRepository->findById(new ProjectId($command->projectId));
        if ($project === null) {
            throw new RuntimeException('Project not found: '.$command->projectId);
        }

        $ref = new ReflectionClass($project);
        $ref->getProperty('name')->setValue($project, new ProjectName($command->name));

        $this->projectRepository->save($project);

        return ProjectDto::fromDomain($project);
    }
}
