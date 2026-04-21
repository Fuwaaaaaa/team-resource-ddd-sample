<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\AllocationModel;
use App\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProjectModel;
use App\Infrastructure\Persistence\Eloquent\Models\SkillModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * タイムライン/ガント表示用のリソース割当ビュー。
 * periodStart / periodEnd の窓と重なる active アロケーションをメンバー別に返す。
 */
class TimelineController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'periodStart' => ['required', Rule::date()->format('Y-m-d')],
            'periodEnd' => ['required', Rule::date()->format('Y-m-d')->afterOrEqual('periodStart')],
        ]);

        $windowStart = $validated['periodStart'];
        $windowEnd = $validated['periodEnd'];

        $allocations = AllocationModel::query()
            ->where('status', 'active')
            ->whereDate('period_start', '<=', $windowEnd)
            ->whereDate('period_end', '>=', $windowStart)
            ->orderBy('period_start')
            ->get();

        $memberIds = $allocations->pluck('member_id')->unique()->all();
        $projectIds = $allocations->pluck('project_id')->unique()->all();
        $skillIds = $allocations->pluck('skill_id')->unique()->all();

        $members = MemberModel::query()->whereIn('id', $memberIds)->get()->keyBy('id');
        $projects = ProjectModel::query()->whereIn('id', $projectIds)->get()->keyBy('id');
        $skills = SkillModel::query()->whereIn('id', $skillIds)->get()->keyBy('id');

        $grouped = $allocations->groupBy('member_id');

        $rows = [];
        foreach ($grouped as $memberId => $memberAllocations) {
            $member = $members->get($memberId);
            $rows[] = [
                'memberId' => (string) $memberId,
                'memberName' => $member?->name ?? 'Unknown',
                'allocations' => $memberAllocations->map(function (AllocationModel $a) use ($projects, $skills): array {
                    $project = $projects->get($a->project_id);
                    $skill = $skills->get($a->skill_id);

                    return [
                        'id' => (string) $a->id,
                        'projectId' => (string) $a->project_id,
                        'projectName' => $project?->name ?? 'Unknown',
                        'skillId' => (string) $a->skill_id,
                        'skillName' => $skill?->name ?? 'Unknown',
                        'percentage' => (int) $a->allocation_percentage,
                        'periodStart' => $a->period_start?->format('Y-m-d'),
                        'periodEnd' => $a->period_end?->format('Y-m-d'),
                    ];
                })->values()->all(),
            ];
        }

        // メンバー名でソート
        usort($rows, fn ($a, $b) => strcmp($a['memberName'], $b['memberName']));

        return response()->json([
            'periodStart' => $windowStart,
            'periodEnd' => $windowEnd,
            'rows' => $rows,
        ]);
    }
}
