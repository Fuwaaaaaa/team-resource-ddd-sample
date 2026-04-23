<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Mappers;

use App\Domain\Project\Project;
use App\Domain\Project\ProjectId;
use App\Domain\Project\ProjectName;
use App\Domain\Project\ProjectStatus;
use App\Domain\Project\RequiredProficiency;
use App\Domain\Project\RequiredSkill;
use App\Domain\Project\RequiredSkillId;
use App\Domain\Skill\SkillId;
use App\Infrastructure\Persistence\Eloquent\Models\ProjectModel;
use App\Infrastructure\Persistence\Eloquent\Models\RequiredSkillModel;
use DateTimeImmutable;
use ReflectionClass;

final class ProjectMapper
{
    /**
     * @param  iterable<RequiredSkillModel>  $requiredSkillModels
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

        $plannedStart = self::toDateImmutable($model->planned_start_date);
        $plannedEnd = self::toDateImmutable($model->planned_end_date);

        $props = [
            'id' => new ProjectId((string) $model->id),
            'name' => new ProjectName((string) $model->name),
            'status' => ProjectStatus::from((string) ($model->status ?? 'active')),
            'plannedStartDate' => $plannedStart,
            'plannedEndDate' => $plannedEnd,
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
            'status' => $project->status()->value,
            'planned_start_date' => $project->plannedStartDate()?->format('Y-m-d'),
            'planned_end_date' => $project->plannedEndDate()?->format('Y-m-d'),
        ];
    }

    private static function toDateImmutable(mixed $raw): ?DateTimeImmutable
    {
        if ($raw === null) {
            return null;
        }
        if ($raw instanceof \DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($raw);
        }

        return new DateTimeImmutable((string) $raw);
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
