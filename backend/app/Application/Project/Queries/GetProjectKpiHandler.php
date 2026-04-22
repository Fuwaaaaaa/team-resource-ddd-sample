<?php

declare(strict_types=1);

namespace App\Application\Project\Queries;

use App\Application\Project\DTOs\ProjectKpiDto;
use App\Domain\Allocation\ResourceAllocationRepositoryInterface;
use App\Domain\Member\MemberRepositoryInterface;
use App\Domain\Project\ProjectId;
use App\Domain\Project\ProjectRepositoryInterface;
use App\Domain\Service\AllocationServiceInterface;
use App\Domain\Skill\SkillRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * プロジェクト別 KPI を算出する。
 *
 * - 必要スキルごとの required vs qualified、ギャップ
 * - 総合充足率 (全 RequiredSkill で qualified/required を加重平均)
 * - アクティブなアロケーション数と延べ人月（稼働期間日数 × percentage / 30）
 * - 30 日以内に終了するアロケーション (次アサイン計画の注意喚起)
 */
final class GetProjectKpiHandler
{
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
        private MemberRepositoryInterface $memberRepository,
        private ResourceAllocationRepositoryInterface $allocationRepository,
        private SkillRepositoryInterface $skillRepository,
        private AllocationServiceInterface $allocationService,
    ) {}

    public function handle(GetProjectKpiQuery $query): ProjectKpiDto
    {
        $projectId = new ProjectId($query->projectId);
        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            throw new InvalidArgumentException("Project not found: {$query->projectId}");
        }

        $referenceDate = new DateTimeImmutable($query->referenceDate);
        $members = $this->memberRepository->findAll();
        $allocations = $this->allocationRepository->findByProjectId($projectId);

        // スキル名を解決するためにスキル一覧を取得
        $skillNames = [];
        foreach ($this->skillRepository->findAll() as $s) {
            $skillNames[$s->id()->toString()] = $s->name()->toString();
        }

        // メンバー名解決
        $memberNames = [];
        foreach ($members as $m) {
            $memberNames[$m->id()->toString()] = $m->name()->toString();
        }

        $deficit = $this->allocationService->calculateSurplusDeficit(
            $project,
            $allocations,
            $members,
            $referenceDate,
        );

        $breakdown = [];
        $totalRequired = 0;
        $totalQualified = 0;
        foreach ($deficit->entries() as $entry) {
            $skillIdStr = $entry->skillId()->toString();
            $req = $entry->requiredHeadcount();
            $qualified = $entry->qualifiedHeadcount();
            $breakdown[] = [
                'skillId' => $skillIdStr,
                'skillName' => $skillNames[$skillIdStr] ?? $skillIdStr,
                'requiredHeadcount' => $req,
                'qualifiedHeadcount' => $qualified,
                'gap' => $qualified - $req,
            ];
            $totalRequired += $req;
            $totalQualified += min($qualified, $req); // 過剰は加算しない
        }
        $fulfillment = $totalRequired > 0 ? round(($totalQualified / $totalRequired) * 100, 1) : 100.0;

        $activeCount = 0;
        $personMonths = 0.0;
        $upcoming = [];
        foreach ($allocations as $a) {
            if (! $a->isActive()) {
                continue;
            }
            $activeCount++;

            $start = $a->period()->startDate();
            $end = $a->period()->endDate();
            $days = (int) $start->diff($end)->days + 1;
            $personMonths += ($days * $a->percentage()->value()) / 100.0 / 30.0;

            $daysToEnd = (int) $referenceDate->diff($end)->days;
            if ($end >= $referenceDate && $daysToEnd <= 30) {
                $upcoming[] = [
                    'allocationId' => $a->id()->toString(),
                    'memberId' => $a->memberId()->toString(),
                    'memberName' => $memberNames[$a->memberId()->toString()] ?? 'Unknown',
                    'daysRemaining' => $daysToEnd,
                    'endDate' => $end->format('Y-m-d'),
                ];
            }
        }

        // 残日数の昇順
        usort($upcoming, fn ($a, $b) => $a['daysRemaining'] <=> $b['daysRemaining']);

        return new ProjectKpiDto(
            projectId: $project->id()->toString(),
            projectName: $project->name()->toString(),
            status: $project->status()->value,
            referenceDate: $query->referenceDate,
            fulfillmentRate: $fulfillment,
            totalRequiredHeadcount: $totalRequired,
            totalQualifiedHeadcount: $totalQualified,
            activeAllocationCount: $activeCount,
            personMonthsAllocated: round($personMonths, 2),
            requiredSkillsBreakdown: $breakdown,
            upcomingEnds: $upcoming,
        );
    }
}
