<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\ProjectModel;
use App\Infrastructure\Persistence\Eloquent\Models\RequiredSkillModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::projects() as $projectName => $requiredSkills) {
            $projectId = self::projectId($projectName);

            ProjectModel::updateOrCreate(
                ['id' => $projectId],
                ['name' => $projectName],
            );

            foreach ($requiredSkills as $skillName => $req) {
                RequiredSkillModel::updateOrCreate(
                    [
                        'project_id' => $projectId,
                        'skill_id' => SkillSeeder::skillId($skillName),
                    ],
                    [
                        'id' => (string) Str::uuid5(Str::uuid5Namespace('dns'), "required_skill:{$projectName}:{$skillName}"),
                        'required_proficiency' => $req['proficiency'],
                        'headcount' => $req['headcount'],
                    ],
                );
            }
        }
    }

    public static function projectId(string $name): string
    {
        return (string) Str::uuid5(Str::uuid5Namespace('dns'), 'project:' . $name);
    }

    /** @return array<string, array<string, array{proficiency:int, headcount:int}>> */
    public static function projects(): array
    {
        return [
            'Resource Dashboard Revamp' => [
                'TypeScript' => ['proficiency' => 4, 'headcount' => 2],
                'React' => ['proficiency' => 4, 'headcount' => 2],
                'Next.js' => ['proficiency' => 3, 'headcount' => 2],
                'UI Design' => ['proficiency' => 4, 'headcount' => 1],
            ],
            'Internal Billing Platform' => [
                'PHP' => ['proficiency' => 4, 'headcount' => 2],
                'Laravel' => ['proficiency' => 4, 'headcount' => 2],
                'PostgreSQL' => ['proficiency' => 3, 'headcount' => 1],
                'Docker' => ['proficiency' => 3, 'headcount' => 1],
            ],
            'Analytics Data Pipeline' => [
                'Python' => ['proficiency' => 4, 'headcount' => 2],
                'AWS' => ['proficiency' => 4, 'headcount' => 1],
                'PostgreSQL' => ['proficiency' => 3, 'headcount' => 1],
                'Go' => ['proficiency' => 3, 'headcount' => 1],
            ],
        ];
    }
}
