<?php

declare(strict_types=1);

namespace App\Application\Allocation\Queries;

use App\Application\Allocation\DTOs\AllocationCandidateDto;
use App\Application\Allocation\DTOs\AllocationSuggestionsResultDto;
use App\Application\Allocation\DTOs\RecentAssignmentDto;
use App\Domain\Allocation\ResourceAllocation;
use App\Domain\Allocation\ResourceAllocationRepositoryInterface;
use App\Domain\Member\Member;
use App\Domain\Member\MemberRepositoryInterface;
use App\Domain\Project\ProjectId;
use App\Domain\Project\ProjectRepositoryInterface;
use App\Domain\Service\AllocationCandidate;
use App\Domain\Service\AllocationServiceInterface;
use App\Domain\Skill\SkillId;
use DateTimeImmutable;

final class SuggestAllocationCandidatesHandler
{
    /** 直近何日以内のアサインを recentAssignments として返すか */
    private const RECENT_WINDOW_DAYS = 90;

    /** 1 候補あたり返す recentAssignments の最大件数 */
    private const RECENT_LIMIT_PER_CANDIDATE = 5;

    public function __construct(
        private MemberRepositoryInterface $memberRepository,
        private ResourceAllocationRepositoryInterface $allocationRepository,
        private ProjectRepositoryInterface $projectRepository,
        private AllocationServiceInterface $allocationService,
    ) {}

    public function handle(SuggestAllocationCandidatesQuery $query): AllocationSuggestionsResultDto
    {
        $members = $this->memberRepository->findAll();
        $memberMap = [];
        foreach ($members as $m) {
            $memberMap[$m->id()->toString()] = $m;
        }

        $periodStart = new DateTimeImmutable($query->periodStart);
        $allAllocations = [];
        foreach ($members as $m) {
            $allAllocations = array_merge($allAllocations, $this->allocationRepository->findByMemberId($m->id()));
        }

        $skillId = new SkillId($query->skillId);

        $candidates = $this->allocationService->suggestCandidates(
            $skillId,
            $query->minimumProficiency,
            new ProjectId($query->projectId),
            $periodStart,
            $members,
            $allAllocations,
            $query->limit,
        );

        if (count($candidates) === 0) {
            $hint = $this->diagnoseEmptyResult($skillId, $query->minimumProficiency, $periodStart, $members, $allAllocations);

            return new AllocationSuggestionsResultDto(candidates: [], hint: $hint);
        }

        $projectNames = $this->resolveProjectNamesForRecentAssignments($candidates, $allAllocations, $skillId, $periodStart);

        $dtos = [];
        foreach ($candidates as $c) {
            $member = $memberMap[$c->memberId()->toString()] ?? null;
            $name = $member?->name()->toString() ?? 'Unknown';
            $recent = $this->buildRecentAssignmentsFor($c->memberId()->toString(), $allAllocations, $skillId, $periodStart, $projectNames);
            $dtos[] = AllocationCandidateDto::fromDomain($c, $name, $recent);
        }

        return new AllocationSuggestionsResultDto(candidates: $dtos, hint: null);
    }

    /**
     * 候補 0 件のときの理由分類。UI が「条件を緩めると候補が出るかも」と促せるように。
     *
     * @param  iterable<Member>  $members
     * @param  ResourceAllocation[]  $allAllocations
     */
    private function diagnoseEmptyResult(
        SkillId $skillId,
        int $minimumProficiency,
        DateTimeImmutable $periodStart,
        iterable $members,
        array $allAllocations,
    ): string {
        $anyHasSkill = false;
        $anyMeetsProficiency = false;
        $anyHasCapacity = false;

        foreach ($members as $member) {
            $proficiency = $member->proficiencyFor($skillId);
            if ($proficiency === null) {
                continue;
            }
            $anyHasSkill = true;

            if ($proficiency->level() < $minimumProficiency) {
                continue;
            }
            $anyMeetsProficiency = true;

            $used = 0;
            foreach ($allAllocations as $alloc) {
                if (! $alloc->memberId()->equals($member->id())) {
                    continue;
                }
                if ($alloc->isActive() && $alloc->coversDate($periodStart)) {
                    $used += $alloc->percentage()->value();
                }
            }
            if ($used < 100) {
                $anyHasCapacity = true;
                break;
            }
        }

        if (! $anyHasSkill) {
            return 'no_members_with_skill';
        }
        if (! $anyMeetsProficiency) {
            return 'min_proficiency_too_high';
        }
        if (! $anyHasCapacity) {
            return 'all_members_at_capacity';
        }

        return 'min_proficiency_too_high'; // 説明可能な分岐に落ちなかった場合の保守的な既定
    }

    /**
     * 候補メンバーが対象 skill で過去 90 日に持っていた allocation を抽出し、
     * その allocation が指す project の name を bulk lookup する。
     *
     * @param  AllocationCandidate[]  $candidates
     * @param  ResourceAllocation[]  $allAllocations
     * @return array<string, string> [projectId => projectName]
     */
    private function resolveProjectNamesForRecentAssignments(array $candidates, array $allAllocations, SkillId $skillId, DateTimeImmutable $periodStart): array
    {
        $candidateMemberIds = [];
        foreach ($candidates as $c) {
            $candidateMemberIds[$c->memberId()->toString()] = true;
        }

        $threshold = $periodStart->modify('-'.self::RECENT_WINDOW_DAYS.' days');
        $projectIds = [];
        foreach ($allAllocations as $alloc) {
            if (! isset($candidateMemberIds[$alloc->memberId()->toString()])) {
                continue;
            }
            if (! $alloc->skillId()->equals($skillId)) {
                continue;
            }
            // period が直近窓と少しでも重なっていれば対象 (active / revoked 問わず)
            if ($alloc->period()->endDate() < $threshold) {
                continue;
            }
            $projectIds[$alloc->projectId()->toString()] = true;
        }

        if (count($projectIds) === 0) {
            return [];
        }

        $names = [];
        foreach (array_keys($projectIds) as $pid) {
            $project = $this->projectRepository->findById(new ProjectId($pid));
            if ($project !== null) {
                $names[$pid] = $project->name()->toString();
            }
        }

        return $names;
    }

    /**
     * 1 候補ぶんの recentAssignments を組み立てる。
     * period.endDate が新しい順に最大 5 件。
     *
     * @param  ResourceAllocation[]  $allAllocations
     * @param  array<string, string>  $projectNames
     * @return RecentAssignmentDto[]
     */
    private function buildRecentAssignmentsFor(string $memberId, array $allAllocations, SkillId $skillId, DateTimeImmutable $periodStart, array $projectNames): array
    {
        $threshold = $periodStart->modify('-'.self::RECENT_WINDOW_DAYS.' days');
        $assignments = [];
        foreach ($allAllocations as $alloc) {
            if ($alloc->memberId()->toString() !== $memberId) {
                continue;
            }
            if (! $alloc->skillId()->equals($skillId)) {
                continue;
            }
            if ($alloc->period()->endDate() < $threshold) {
                continue;
            }
            $assignments[] = $alloc;
        }

        usort($assignments, fn (ResourceAllocation $a, ResourceAllocation $b) => $b->period()->endDate() <=> $a->period()->endDate());
        $assignments = array_slice($assignments, 0, self::RECENT_LIMIT_PER_CANDIDATE);

        return array_map(fn (ResourceAllocation $a) => new RecentAssignmentDto(
            projectId: $a->projectId()->toString(),
            projectName: $projectNames[$a->projectId()->toString()] ?? 'Unknown project',
            allocationPercentage: $a->percentage()->value(),
            periodStart: $a->period()->startDate()->format('Y-m-d'),
            periodEnd: $a->period()->endDate()->format('Y-m-d'),
            status: $a->isActive() ? 'active' : 'revoked',
        ), $assignments);
    }
}
