<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\AllocationModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 過負荷メンバーとスキルギャップが両方再現されるアロケーション。
 * - Alice: 60% + 50% = 110% → 過負荷
 * - Fumi: Next.js 2/3 要求 → スキルギャップ
 * - Haruka: 6時間稼働、40% のみ
 */
class AllocationSeeder extends Seeder
{
    public function run(): void
    {
        $referenceStart = '2026-04-01';
        $referenceEnd = '2026-09-30';

        $allocations = [
            // Alice は 60% + 50% = 110% で過負荷
            ['Alice Tanaka', 'Resource Dashboard Revamp', 'React', 60],
            ['Alice Tanaka', 'Analytics Data Pipeline', 'TypeScript', 50],

            // Fumi は Next.js 2（要求3） → スキルギャップ
            ['Fumi Ito', 'Resource Dashboard Revamp', 'Next.js', 50],

            // Bob は Laravel プロジェクトをほぼ一人で支える
            ['Bob Suzuki', 'Internal Billing Platform', 'Laravel', 80],

            // Carol は Go/Docker でデータパイプライン
            ['Carol Yamada', 'Analytics Data Pipeline', 'Go', 50],

            // Daichi は Python/AWS で半分
            ['Daichi Kobayashi', 'Analytics Data Pipeline', 'Python', 50],
            ['Daichi Kobayashi', 'Internal Billing Platform', 'Docker', 30],

            // Eri は UI 専任
            ['Eri Nakamura', 'Resource Dashboard Revamp', 'UI Design', 70],

            // Gou は PM
            ['Gou Watanabe', 'Resource Dashboard Revamp', 'Project Management', 30],
            ['Gou Watanabe', 'Internal Billing Platform', 'Project Management', 30],

            // Haruka は時短、40%
            ['Haruka Sato', 'Internal Billing Platform', 'PHP', 40],
        ];

        foreach ($allocations as [$memberName, $projectName, $skillName, $pct]) {
            $id = (string) Str::uuid5(
                Str::uuid5Namespace('dns'),
                "allocation:{$memberName}:{$projectName}:{$skillName}",
            );
            AllocationModel::updateOrCreate(
                ['id' => $id],
                [
                    'member_id' => MemberSeeder::memberId($memberName),
                    'project_id' => ProjectSeeder::projectId($projectName),
                    'skill_id' => SkillSeeder::skillId($skillName),
                    'allocation_percentage' => $pct,
                    'period_start' => $referenceStart,
                    'period_end' => $referenceEnd,
                    'status' => 'active',
                ],
            );
        }
    }
}
