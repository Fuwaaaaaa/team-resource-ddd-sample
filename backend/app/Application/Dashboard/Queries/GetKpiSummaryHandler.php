<?php

declare(strict_types=1);

namespace App\Application\Dashboard\Queries;

use App\Application\Dashboard\DTOs\KpiSummaryDto;
use App\Domain\Allocation\ResourceAllocationRepositoryInterface;
use App\Domain\Availability\AbsenceRepositoryInterface;
use App\Domain\Member\MemberRepositoryInterface;
use App\Domain\Project\ProjectRepositoryInterface;
use App\Domain\Service\AllocationServiceInterface;
use DateInterval;
use DateTimeImmutable;

/**
 * Dashboard 上部に表示する KPI サマリを算出する。
 *
 * - 全 active/planning プロジェクトの充足率平均
 * - 過負荷メンバー数 (不在考慮済み)
 * - 7 日以内に終了するアクティブアサイン数
 * - 全 active/planning プロジェクトで不足している人数の合計
 */
final class GetKpiSummaryHandler
{
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
        private MemberRepositoryInterface $memberRepository,
        private ResourceAllocationRepositoryInterface $allocationRepository,
        private AbsenceRepositoryInterface $absenceRepository,
        private AllocationServiceInterface $allocationService,
    ) {}

    public function handle(GetKpiSummaryQuery $query): KpiSummaryDto
    {
        $referenceDate = new DateTimeImmutable($query->referenceDate);
        $weekLater = $referenceDate->add(new DateInterval('P7D'));

        $members = $this->memberRepository->findAll();

        // 集計対象は KPI カウント対象 (planning / active)
        $eligibleProjects = array_values(array_filter(
            $this->projectRepository->findAll(),
            fn ($p) => $p->status()->countsForCapacity(),
        ));

        $fulfillmentRates = [];
        $skillGapsTotal = 0;
        foreach ($eligibleProjects as $project) {
            $allocs = $this->allocationRepository->findByProjectId($project->id());
            $deficit = $this->allocationService->calculateSurplusDeficit(
                $project,
                $allocs,
                $members,
                $referenceDate,
            );

            $required = 0;
            $qualified = 0;
            foreach ($deficit->entries() as $entry) {
                $required += $entry->requiredHeadcount();
                $qualified += min($entry->qualifiedHeadcount(), $entry->requiredHeadcount());
                if ($entry->gap() < 0) {
                    $skillGapsTotal += -$entry->gap();
                }
            }
            $fulfillmentRates[] = $required > 0 ? ($qualified / $required) * 100.0 : 100.0;
        }

        $averageFulfillment = count($fulfillmentRates) > 0
            ? round(array_sum($fulfillmentRates) / count($fulfillmentRates), 1)
            : 100.0;

        // Overload
        $activeAllocations = $this->allocationRepository->findActiveOnDate($referenceDate);
        $absences = $this->absenceRepository->findActive();
        $overloadAnalysis = $this->allocationService->detectOverload(
            $members,
            $activeAllocations,
            $referenceDate,
            $absences,
        );
        $overloadedCount = count($overloadAnalysis->overloadedMembers());

        // Upcoming ends (7 日以内)
        $upcomingEnds = 0;
        foreach ($activeAllocations as $a) {
            $end = $a->period()->endDate();
            if ($end >= $referenceDate && $end <= $weekLater) {
                $upcomingEnds++;
            }
        }

        return new KpiSummaryDto(
            referenceDate: $query->referenceDate,
            averageFulfillmentRate: $averageFulfillment,
            activeProjectCount: count($eligibleProjects),
            overloadedMemberCount: $overloadedCount,
            upcomingEndsThisWeek: $upcomingEnds,
            skillGapsTotal: $skillGapsTotal,
        );
    }
}
