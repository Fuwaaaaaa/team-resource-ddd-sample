<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use App\Infrastructure\Persistence\Eloquent\Models\MemberSkillModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MemberSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::members() as $memberName => $def) {
            $memberId = self::memberId($memberName);

            MemberModel::updateOrCreate(
                ['id' => $memberId],
                [
                    'name' => $memberName,
                    'standard_working_hours' => $def['hours'],
                ],
            );

            foreach ($def['skills'] as $skillName => $proficiency) {
                MemberSkillModel::updateOrCreate(
                    [
                        'member_id' => $memberId,
                        'skill_id' => SkillSeeder::skillId($skillName),
                    ],
                    [
                        'id' => (string) Str::uuid5(Str::uuid5Namespace('dns'), "member_skill:{$memberName}:{$skillName}"),
                        'proficiency' => $proficiency,
                    ],
                );
            }
        }
    }

    public static function memberId(string $name): string
    {
        return (string) Str::uuid5(Str::uuid5Namespace('dns'), 'member:'.$name);
    }

    /** @return array<string, array{hours: float, skills: array<string, int>}> */
    public static function members(): array
    {
        return [
            'Alice Tanaka' => ['hours' => 8.0, 'skills' => [
                'TypeScript' => 5, 'React' => 5, 'Next.js' => 4, 'UI Design' => 3,
            ]],
            'Bob Suzuki' => ['hours' => 8.0, 'skills' => [
                'PHP' => 5, 'Laravel' => 5, 'PostgreSQL' => 4, 'MySQL' => 4,
            ]],
            'Carol Yamada' => ['hours' => 8.0, 'skills' => [
                'Go' => 4, 'Docker' => 4, 'AWS' => 3, 'PostgreSQL' => 3,
            ]],
            'Daichi Kobayashi' => ['hours' => 8.0, 'skills' => [
                'Python' => 4, 'AWS' => 4, 'Docker' => 3, 'PostgreSQL' => 3,
            ]],
            'Eri Nakamura' => ['hours' => 7.5, 'skills' => [
                'UI Design' => 5, 'UX Research' => 5, 'Project Management' => 3,
            ]],
            'Fumi Ito' => ['hours' => 8.0, 'skills' => [
                'TypeScript' => 3, 'React' => 3, 'Next.js' => 2,
            ]],
            'Gou Watanabe' => ['hours' => 8.0, 'skills' => [
                'Project Management' => 5, 'Technical Writing' => 4, 'UX Research' => 2,
            ]],
            'Haruka Sato' => ['hours' => 6.0, 'skills' => [
                'Laravel' => 3, 'PHP' => 3, 'MySQL' => 2, 'Docker' => 2,
            ]],
        ];
    }
}
