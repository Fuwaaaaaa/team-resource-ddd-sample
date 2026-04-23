<?php

declare(strict_types=1);

namespace App\Application\Project\Commands;

use App\Application\Project\DTOs\ProjectDto;
use App\Domain\Project\Project;
use App\Domain\Project\ProjectName;
use App\Domain\Project\ProjectRepositoryInterface;
use DateTimeImmutable;

final class CreateProjectHandler
{
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
    ) {}

    public function handle(CreateProjectCommand $command): ProjectDto
    {
        $id = $this->projectRepository->nextIdentity();
        $project = new Project($id, new ProjectName($command->name));

        if ($command->plannedStartDate !== null && $command->plannedEndDate !== null) {
            $project->setPlannedPeriod(
                new DateTimeImmutable($command->plannedStartDate),
                new DateTimeImmutable($command->plannedEndDate),
            );
        }

        $this->projectRepository->save($project);

        return ProjectDto::fromDomain($project);
    }
}
