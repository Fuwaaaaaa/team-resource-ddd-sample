<?php

declare(strict_types=1);

namespace App\Application\Dashboard\Queries;

use App\Application\Dashboard\DTOs\MemberOverloadDto;
use App\Application\Dashboard\DTOs\OverloadAnalysisDto;
use App\Domain\Allocation\ResourceAllocationRepositoryInterface;
use App\Domain\Availability\AbsenceRepositoryInterface;
use App\Domain\Member\MemberRepositoryInterface;
use App\Domain\Service\AllocationServiceInterface;
use DateTimeImmutable;

final class GetOverloadAnalysisHandler
{
    public function __construct(
        private MemberRepositoryInterface $memberRepository,
        private ResourceAllocationRepositoryInterface $allocationRepository,
        private AbsenceRepositoryInterface $absenceRepository,
        private AllocationServiceInterface $allocationService,
    ) {
    }

    public function handle(GetOverloadAnalysisQuery $query): OverloadAnalysisDto
    {
        $referenceDate = new DateTimeImmutable($query->referenceDate);
        $members = $this->memberRepository->findAll();
        $allocations = $this->allocationRepository->findActiveOnDate($referenceDate);

        // 基準日に該当する有効な不在のみ取得（findActive() は全件、絞り込みは Service 側の coversDate で行う）
        $absences = $this->absenceRepository->findActive();

        $analysis = $this->allocationService->detectOverload(
            $members,
            $allocations,
            $referenceDate,
            $absences,
        );

        // ドメイン結果 → DTO変換（メンバー名解決含む）
        $memberMap = [];
        foreach ($members as $member) {
            $memberMap[$member->id()->toString()] = $member;
        }

        $memberDtos = [];
        foreach ($analysis->entries() as $entry) {
            $member = $memberMap[$entry->memberId()->toString()];
            $memberDtos[] = new MemberOverloadDto(
                memberId: $entry->memberId()->toString(),
                memberName: $member->name()->toString(),
                standardHoursPerDay: $entry->standardHoursPerDay(),
                totalAllocatedPercentage: $entry->totalAllocatedPercentage(),
                allocatedHoursPerDay: $member->standardWorkingHours()->toHours(
                    $entry->totalAllocatedPercentage()
                ),
                overloadHours: $entry->overloadHours(),
                isOverloaded: $entry->isOverloaded(),
            );
        }

        return new OverloadAnalysisDto(
            members: $memberDtos,
            overloadedCount: count($analysis->overloadedMembers()),
            referenceDate: $query->referenceDate,
        );
    }
}
