<?php

declare(strict_types=1);

namespace App\Application\Project\DTOs;

final class ProjectKpiDto
{
    /**
     * @param  array<int, array{skillId:string,skillName:string,requiredHeadcount:int,qualifiedHeadcount:int,gap:int}>  $requiredSkillsBreakdown
     * @param  array<int, array{allocationId:string,memberId:string,memberName:string,daysRemaining:int,endDate:string}>  $upcomingEnds
     */
    public function __construct(
        public readonly string $projectId,
        public readonly string $projectName,
        public readonly string $status,
        public readonly string $referenceDate,
        public readonly float $fulfillmentRate,       // 0-100 (%)
        public readonly int $totalRequiredHeadcount,
        public readonly int $totalQualifiedHeadcount,
        public readonly int $activeAllocationCount,
        public readonly float $personMonthsAllocated, // 合計人月 (概算)
        public readonly array $requiredSkillsBreakdown,
        public readonly array $upcomingEnds,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'projectId' => $this->projectId,
            'projectName' => $this->projectName,
            'status' => $this->status,
            'referenceDate' => $this->referenceDate,
            'fulfillmentRate' => $this->fulfillmentRate,
            'totalRequiredHeadcount' => $this->totalRequiredHeadcount,
            'totalQualifiedHeadcount' => $this->totalQualifiedHeadcount,
            'activeAllocationCount' => $this->activeAllocationCount,
            'personMonthsAllocated' => $this->personMonthsAllocated,
            'requiredSkillsBreakdown' => $this->requiredSkillsBreakdown,
            'upcomingEnds' => $this->upcomingEnds,
        ];
    }
}
