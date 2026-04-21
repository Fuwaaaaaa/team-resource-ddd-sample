<?php

declare(strict_types=1);

namespace App\Application\Dashboard\Queries;

use App\Application\Dashboard\DTOs\MemberCapacityEntryDto;
use App\Application\Dashboard\DTOs\SkillDto;
use App\Application\Dashboard\DTOs\TeamCapacitySnapshotDto;
use App\Domain\Allocation\ResourceAllocationRepositoryInterface;
use App\Domain\Member\MemberRepositoryInterface;
use App\Domain\Service\AllocationServiceInterface;
use App\Domain\Skill\SkillRepositoryInterface;
use DateTimeImmutable;

final class GetTeamCapacityHandler
{
    public function __construct(
        private MemberRepositoryInterface $memberRepository,
        private ResourceAllocationRepositoryInterface $allocationRepository,
        private SkillRepositoryInterface $skillRepository,
        private AllocationServiceInterface $allocationService,
    ) {
    }

    public function handle(GetTeamCapacityQuery $query): TeamCapacitySnapshotDto
    {
        $referenceDate = new DateTimeImmutable($query->referenceDate);
        $members = $this->memberRepository->findAll();
        $allocations = $this->allocationRepository->findActiveOnDate($referenceDate);
        $skills = $this->skillRepository->findAll();

        $snapshot = $this->allocationService->buildTeamCapacitySnapshot(
            $members,
            $allocations,
            $referenceDate,
        );

        $memberMap = [];
        foreach ($members as $member) {
            $memberMap[$member->id()->toString()] = $member;
        }

        $entryDtos = [];
        foreach ($snapshot->entries() as $entry) {
            $member = $memberMap[$entry->memberId()->toString()] ?? null;
            $entryDtos[] = new MemberCapacityEntryDto(
                memberId: $entry->memberId()->toString(),
                memberName: $member?->name()->toString() ?? 'Unknown',
                availablePercentage: $entry->availablePercentage(),
                skillProficiencies: $entry->skillProficiencies(),
            );
        }

        $skillDtos = [];
        foreach ($skills as $skill) {
            $skillDtos[] = new SkillDto(
                id: $skill->id()->toString(),
                name: $skill->name()->toString(),
                category: $skill->category()->toString(),
            );
        }

        return new TeamCapacitySnapshotDto(
            entries: $entryDtos,
            skills: $skillDtos,
            referenceDate: $query->referenceDate,
        );
    }
}
