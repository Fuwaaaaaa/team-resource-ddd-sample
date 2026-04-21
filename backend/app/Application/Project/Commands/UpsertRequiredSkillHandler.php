<?php

declare(strict_types=1);

namespace App\Application\Project\Commands;

use App\Application\Project\DTOs\ProjectDto;
use App\Domain\Project\ProjectId;
use App\Domain\Project\ProjectRepositoryInterface;
use App\Domain\Project\RequiredProficiency;
use App\Domain\Project\RequiredSkillId;
use App\Domain\Skill\SkillId;
use Illuminate\Support\Str;
use RuntimeException;

final class UpsertRequiredSkillHandler
{
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
    ) {
    }

    public function handle(UpsertRequiredSkillCommand $command): ProjectDto
    {
        $project = $this->projectRepository->findById(new ProjectId($command->projectId));
        if ($project === null) {
            throw new RuntimeException('Project not found: ' . $command->projectId);
        }

        $project->addOrUpdateRequirement(
            new RequiredSkillId((string) Str::uuid()),
            new SkillId($command->skillId),
            new RequiredProficiency($command->requiredProficiency),
            $command->headcount,
        );

        $this->projectRepository->save($project);

        return ProjectDto::fromDomain($project);
    }
}
