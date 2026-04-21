<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Project\Project;
use App\Domain\Project\ProjectId;
use App\Domain\Project\ProjectRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Mappers\ProjectMapper;
use App\Infrastructure\Persistence\Eloquent\Models\ProjectModel;
use App\Infrastructure\Persistence\Eloquent\Models\RequiredSkillModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentProjectRepository implements ProjectRepositoryInterface
{
    public function findById(ProjectId $id): ?Project
    {
        $model = ProjectModel::with('requiredSkills')->find($id->toString());
        return $model ? ProjectMapper::toDomain($model, $model->requiredSkills) : null;
    }

    /** @return Project[] */
    public function findAll(): array
    {
        return ProjectModel::with('requiredSkills')->orderBy('name')->get()
            ->map(fn (ProjectModel $m) => ProjectMapper::toDomain($m, $m->requiredSkills))
            ->all();
    }

    public function save(Project $project): void
    {
        DB::transaction(function () use ($project): void {
            ProjectModel::updateOrCreate(
                ['id' => $project->id()->toString()],
                ProjectMapper::toRow($project),
            );

            $projectId = $project->id()->toString();
            $keepIds = [];
            foreach (ProjectMapper::requiredSkillsToRows($project) as $row) {
                RequiredSkillModel::updateOrCreate(
                    ['id' => $row['id']],
                    $row,
                );
                $keepIds[] = $row['id'];
            }
            RequiredSkillModel::where('project_id', $projectId)
                ->whereNotIn('id', $keepIds)
                ->delete();
        });
    }

    public function delete(ProjectId $id): void
    {
        ProjectModel::where('id', $id->toString())->delete();
    }

    public function nextIdentity(): ProjectId
    {
        return new ProjectId((string) Str::uuid());
    }
}
