<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Mappers;

use App\Domain\Project\Project;
use App\Domain\Project\ProjectId;
use App\Domain\Project\ProjectName;
use App\Domain\Project\RequiredProficiency;
use App\Domain\Project\RequiredSkill;
use App\Domain\Project\RequiredSkillId;
use App\Domain\Skill\SkillId;
use App\Infrastructure\Persistence\Eloquent\Models\ProjectModel;
use App\Infrastructure\Persistence\Eloquent\Models\RequiredSkillModel;
use ReflectionClass;

final class ProjectMapper
{
    /**
     * @param iterable<RequiredSkillModel> $requiredSkillModels
     */
    public static function toDomain(ProjectModel $model, iterable $requiredSkillModels): Project
    {
        $ref = new ReflectionClass(Project::class);
        /** @var Project $project */
        $project = $ref->newInstanceWithoutConstructor();

        $required = [];
        foreach ($requiredSkillModels as $rs) {
            $required[(string) $rs->skill_id] = new RequiredSkill(
                new RequiredSkillId((string) $rs->id),
                new SkillId((string) $rs->skill_id),
                new RequiredProficiency((int) $rs->required_proficiency),
                (int) $rs->headcount,
            );
        }

        $props = [
            'id' => new ProjectId((string) $model->id),
            'name' => new ProjectName((string) $model->name),
            'requiredSkills' => $required,
            'domainEvents' => [],
        ];

        foreach ($props as $name => $value) {
            $prop = $ref->getProperty($name);
            $prop->setValue($project, $value);
        }

        return $project;
    }

    /** @return array<string, mixed> */
    public static function toRow(Project $project): array
    {
        return [
            'id' => $project->id()->toString(),
            'name' => $project->name()->toString(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function requiredSkillsToRows(Project $project): array
    {
        $rows = [];
        foreach ($project->requiredSkills() as $rs) {
            $rows[] = [
                'id' => $rs->id()->toString(),
                'project_id' => $project->id()->toString(),
                'skill_id' => $rs->skillId()->toString(),
                'required_proficiency' => $rs->minimumProficiency()->level(),
                'headcount' => $rs->headcount(),
            ];
        }
        return $rows;
    }
}
