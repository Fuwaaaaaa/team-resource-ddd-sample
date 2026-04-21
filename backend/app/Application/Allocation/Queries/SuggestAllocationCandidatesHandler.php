<?php

declare(strict_types=1);

namespace App\Application\Allocation\Queries;

use App\Application\Allocation\DTOs\AllocationCandidateDto;
use App\Domain\Allocation\ResourceAllocationRepositoryInterface;
use App\Domain\Member\MemberRepositoryInterface;
use App\Domain\Project\ProjectId;
use App\Domain\Service\AllocationServiceInterface;
use App\Domain\Skill\SkillId;
use DateTimeImmutable;

final class SuggestAllocationCandidatesHandler
{
    public function __construct(
        private MemberRepositoryInterface $memberRepository,
        private ResourceAllocationRepositoryInterface $allocationRepository,
        private AllocationServiceInterface $allocationService,
    ) {}

    /** @return AllocationCandidateDto[] */
    public function handle(SuggestAllocationCandidatesQuery $query): array
    {
        $members = $this->memberRepository->findAll();
        $memberMap = [];
        foreach ($members as $m) {
            $memberMap[$m->id()->toString()] = $m;
        }

        // そのメンバーの全 allocation (revoked 含む) を period 開始日の余剰キャパ算出と経験歴カウントに使う
        $periodStart = new DateTimeImmutable($query->periodStart);
        $allAllocations = [];
        foreach ($members as $m) {
            $allAllocations = array_merge($allAllocations, $this->allocationRepository->findByMemberId($m->id()));
        }

        $candidates = $this->allocationService->suggestCandidates(
            new SkillId($query->skillId),
            $query->minimumProficiency,
            new ProjectId($query->projectId),
            $periodStart,
            $members,
            $allAllocations,
            $query->limit,
        );

        $dtos = [];
        foreach ($candidates as $c) {
            $member = $memberMap[$c->memberId()->toString()] ?? null;
            $name = $member?->name()->toString() ?? 'Unknown';
            $dtos[] = AllocationCandidateDto::fromDomain($c, $name);
        }

        return $dtos;
    }
}
