<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Domain\Allocation\AllocationId;
use App\Domain\Allocation\AllocationPercentage;
use App\Domain\Allocation\AllocationPeriod;
use App\Domain\Allocation\ResourceAllocation;
use App\Domain\Member\Member;
use App\Domain\Member\MemberId;
use App\Domain\Member\MemberName;
use App\Domain\Member\MemberSkillId;
use App\Domain\Member\SkillProficiency;
use App\Domain\Member\StandardWorkingHours;
use App\Domain\Project\Project;
use App\Domain\Project\ProjectId;
use App\Domain\Project\ProjectName;
use App\Domain\Project\RequiredProficiency;
use App\Domain\Project\RequiredSkillId;
use App\Domain\Skill\SkillId;
use DateTimeImmutable;

trait AllocationTestFactory
{
    private int $autoIncrementId = 0;

    private function nextId(): string
    {
        return 'auto-' . (++$this->autoIncrementId);
    }

    protected function makeMember(string $id = 'member-1', float $hours = 8.0): Member
    {
        return new Member(
            new MemberId($id),
            new MemberName($id),
            new StandardWorkingHours($hours)
        );
    }

    protected function addSkillToMember(Member $member, string $skillId, int $proficiency): void
    {
        $member->addOrUpdateSkill(
            new MemberSkillId($this->nextId()),
            new SkillId($skillId),
            new SkillProficiency($proficiency)
        );
    }

    protected function makeProject(string $id = 'project-1'): Project
    {
        return new Project(new ProjectId($id), new ProjectName($id));
    }

    protected function addRequirementToProject(
        Project $project,
        string $skillId,
        int $minProficiency,
        int $headcount
    ): void {
        $project->addOrUpdateRequirement(
            new RequiredSkillId($this->nextId()),
            new SkillId($skillId),
            new RequiredProficiency($minProficiency),
            $headcount
        );
    }

    protected function makeAllocation(
        string $memberId = 'member-1',
        string $projectId = 'project-1',
        string $skillId = 'php',
        int $percentage = 50,
        string $startDate = '2025-01-01',
        string $endDate = '2025-12-31'
    ): ResourceAllocation {
        return new ResourceAllocation(
            new AllocationId($this->nextId()),
            new MemberId($memberId),
            new ProjectId($projectId),
            new SkillId($skillId),
            new AllocationPercentage($percentage),
            new AllocationPeriod(
                new DateTimeImmutable($startDate),
                new DateTimeImmutable($endDate)
            )
        );
    }

    protected function makeRevokedAllocation(
        string $memberId = 'member-1',
        string $projectId = 'project-1',
        string $skillId = 'php',
        int $percentage = 50,
        string $startDate = '2025-01-01',
        string $endDate = '2025-12-31'
    ): ResourceAllocation {
        $allocation = $this->makeAllocation($memberId, $projectId, $skillId, $percentage, $startDate, $endDate);
        $allocation->revoke();
        return $allocation;
    }

    protected function referenceDate(string $date = '2025-06-15'): DateTimeImmutable
    {
        return new DateTimeImmutable($date);
    }
}
