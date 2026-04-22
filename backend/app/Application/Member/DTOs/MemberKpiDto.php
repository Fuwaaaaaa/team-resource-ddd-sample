<?php

declare(strict_types=1);

namespace App\Application\Member\DTOs;

final class MemberKpiDto
{
    /**
     * @param  array<int, array{allocationId:string,projectId:string,projectName:string,skillId:string,skillName:string,percentage:int,startDate:string,endDate:string,daysRemaining:int}>  $activeAllocations
     * @param  array<int, array{allocationId:string,projectId:string,projectName:string,daysRemaining:int,endDate:string}>  $upcomingEnds
     * @param  array<int, array{skillId:string,skillName:string,proficiency:int}>  $skills
     */
    public function __construct(
        public readonly string $memberId,
        public readonly string $memberName,
        public readonly string $referenceDate,
        public readonly int $currentUtilization,      // 0-200+ (%)
        public readonly int $remainingCapacity,       // 0-100 (%), clamped
        public readonly bool $isOverloaded,           // utilization > 100
        public readonly int $activeAllocationCount,
        public readonly array $activeAllocations,
        public readonly array $upcomingEnds,
        public readonly array $skills,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'memberId' => $this->memberId,
            'memberName' => $this->memberName,
            'referenceDate' => $this->referenceDate,
            'currentUtilization' => $this->currentUtilization,
            'remainingCapacity' => $this->remainingCapacity,
            'isOverloaded' => $this->isOverloaded,
            'activeAllocationCount' => $this->activeAllocationCount,
            'activeAllocations' => $this->activeAllocations,
            'upcomingEnds' => $this->upcomingEnds,
            'skills' => $this->skills,
        ];
    }
}
