<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Service;

use App\Domain\Allocation\AllocationPercentage;
use App\Domain\Member\MemberId;
use App\Infrastructure\Service\AllocationService;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\AllocationTestFactory;

final class AllocationServiceTest extends TestCase
{
    use AllocationTestFactory;

    private AllocationService $service;

    protected function setUp(): void
    {
        $this->service = new AllocationService();
    }

    // ================================================================
    // canAllocate
    // ================================================================

    public function test_canAllocate_no_existing_allocations(): void
    {
        $result = $this->service->canAllocate(
            new MemberId('member-1'),
            new AllocationPercentage(50),
            [],
            $this->referenceDate()
        );

        $this->assertTrue($result);
    }

    public function test_canAllocate_exactly_100_percent(): void
    {
        $allocations = [
            $this->makeAllocation('member-1', 'p1', 'php', 60),
        ];

        $result = $this->service->canAllocate(
            new MemberId('member-1'),
            new AllocationPercentage(40),
            $allocations,
            $this->referenceDate()
        );

        $this->assertTrue($result);
    }

    public function test_canAllocate_over_100_percent(): void
    {
        $allocations = [
            $this->makeAllocation('member-1', 'p1', 'php', 60),
        ];

        $result = $this->service->canAllocate(
            new MemberId('member-1'),
            new AllocationPercentage(50),
            $allocations,
            $this->referenceDate()
        );

        $this->assertFalse($result);
    }

    public function test_canAllocate_revoked_allocations_excluded(): void
    {
        $allocations = [
            $this->makeAllocation('member-1', 'p1', 'php', 60),
            $this->makeRevokedAllocation('member-1', 'p2', 'java', 40),
        ];

        $result = $this->service->canAllocate(
            new MemberId('member-1'),
            new AllocationPercentage(40),
            $allocations,
            $this->referenceDate()
        );

        $this->assertTrue($result);
    }

    public function test_canAllocate_out_of_date_range_excluded(): void
    {
        $allocations = [
            $this->makeAllocation('member-1', 'p1', 'php', 80, '2024-01-01', '2024-12-31'),
        ];

        $result = $this->service->canAllocate(
            new MemberId('member-1'),
            new AllocationPercentage(50),
            $allocations,
            $this->referenceDate('2025-06-15')
        );

        $this->assertTrue($result);
    }

    public function test_canAllocate_other_member_excluded(): void
    {
        $allocations = [
            $this->makeAllocation('member-2', 'p1', 'php', 100),
        ];

        $result = $this->service->canAllocate(
            new MemberId('member-1'),
            new AllocationPercentage(50),
            $allocations,
            $this->referenceDate()
        );

        $this->assertTrue($result);
    }

    // ================================================================
    // analyzeSkillGaps
    // ================================================================

    public function test_analyzeSkillGaps_single_project_no_gap(): void
    {
        $project = $this->makeProject();
        $this->addRequirementToProject($project, 'php', 3, 1);

        $member = $this->makeMember('m1');
        $this->addSkillToMember($member, 'php', 3);

        $result = $this->service->analyzeSkillGaps(
            [$project],
            [$member],
            [],
            $this->referenceDate()
        );

        $this->assertCount(1, $result->entries());
        $this->assertSame(0, $result->entries()[0]->gap());
        $this->assertFalse($result->hasCriticalGaps());
    }

    public function test_analyzeSkillGaps_single_project_deficit(): void
    {
        $project = $this->makeProject();
        $this->addRequirementToProject($project, 'php', 3, 2);

        $member = $this->makeMember('m1');
        $this->addSkillToMember($member, 'php', 3);

        $result = $this->service->analyzeSkillGaps(
            [$project],
            [$member],
            [],
            $this->referenceDate()
        );

        $this->assertSame(-1, $result->entries()[0]->gap());
        $this->assertTrue($result->hasCriticalGaps());
    }

    public function test_analyzeSkillGaps_cross_project_aggregation(): void
    {
        $projectA = $this->makeProject('pA');
        $this->addRequirementToProject($projectA, 'php', 2, 1);

        $projectB = $this->makeProject('pB');
        $this->addRequirementToProject($projectB, 'php', 3, 2);

        $member = $this->makeMember('m1');
        $this->addSkillToMember($member, 'php', 3);

        $result = $this->service->analyzeSkillGaps(
            [$projectA, $projectB],
            [$member],
            [],
            $this->referenceDate()
        );

        $entry = $result->entries()[0];
        $this->assertSame(3, $entry->requiredHeadcount());
        $this->assertSame(1, $entry->qualifiedHeadcount());
        $this->assertSame(-2, $entry->gap());
    }

    public function test_analyzeSkillGaps_fully_allocated_member_excluded(): void
    {
        $project = $this->makeProject();
        $this->addRequirementToProject($project, 'php', 3, 1);

        $member = $this->makeMember('m1');
        $this->addSkillToMember($member, 'php', 5);

        $allocations = [
            $this->makeAllocation('m1', 'other-p', 'java', 100),
        ];

        $result = $this->service->analyzeSkillGaps(
            [$project],
            [$member],
            $allocations,
            $this->referenceDate()
        );

        $this->assertSame(0, $result->entries()[0]->qualifiedHeadcount());
    }

    public function test_analyzeSkillGaps_99_percent_member_still_counted(): void
    {
        $project = $this->makeProject();
        $this->addRequirementToProject($project, 'php', 3, 1);

        $member = $this->makeMember('m1');
        $this->addSkillToMember($member, 'php', 3);

        $allocations = [
            $this->makeAllocation('m1', 'other-p', 'java', 99),
        ];

        $result = $this->service->analyzeSkillGaps(
            [$project],
            [$member],
            $allocations,
            $this->referenceDate()
        );

        $this->assertSame(1, $result->entries()[0]->qualifiedHeadcount());
    }

    public function test_analyzeSkillGaps_unqualified_member_not_counted(): void
    {
        $project = $this->makeProject();
        $this->addRequirementToProject($project, 'php', 3, 1);

        $member = $this->makeMember('m1');
        $this->addSkillToMember($member, 'php', 2);

        $result = $this->service->analyzeSkillGaps(
            [$project],
            [$member],
            [],
            $this->referenceDate()
        );

        $this->assertSame(0, $result->entries()[0]->qualifiedHeadcount());
    }

    public function test_analyzeSkillGaps_sorted_by_gap_ascending(): void
    {
        $project = $this->makeProject();
        $this->addRequirementToProject($project, 'java', 3, 2);
        $this->addRequirementToProject($project, 'php', 3, 4);

        $member = $this->makeMember('m1');
        $this->addSkillToMember($member, 'java', 3);
        $this->addSkillToMember($member, 'php', 3);

        $result = $this->service->analyzeSkillGaps(
            [$project],
            [$member],
            [],
            $this->referenceDate()
        );

        $entries = $result->entries();
        // php: gap=-3, java: gap=-1 → php should come first
        $this->assertSame(-3, $entries[0]->gap());
        $this->assertSame(-1, $entries[1]->gap());
    }

    public function test_analyzeSkillGaps_revoked_allocation_not_counted_toward_capacity(): void
    {
        $project = $this->makeProject();
        $this->addRequirementToProject($project, 'php', 3, 1);

        $member = $this->makeMember('m1');
        $this->addSkillToMember($member, 'php', 3);

        $allocations = [
            $this->makeAllocation('m1', 'other-p', 'java', 60),
            $this->makeRevokedAllocation('m1', 'other-p2', 'go', 40),
        ];

        $result = $this->service->analyzeSkillGaps(
            [$project],
            [$member],
            $allocations,
            $this->referenceDate()
        );

        // 60% active only → member is available
        $this->assertSame(1, $result->entries()[0]->qualifiedHeadcount());
    }

    public function test_analyzeSkillGaps_out_of_range_allocation_excluded(): void
    {
        $project = $this->makeProject();
        $this->addRequirementToProject($project, 'php', 3, 1);

        $member = $this->makeMember('m1');
        $this->addSkillToMember($member, 'php', 3);

        $allocations = [
            $this->makeAllocation('m1', 'other-p', 'java', 100, '2024-01-01', '2024-12-31'),
        ];

        $result = $this->service->analyzeSkillGaps(
            [$project],
            [$member],
            $allocations,
            $this->referenceDate('2025-06-15')
        );

        $this->assertSame(1, $result->entries()[0]->qualifiedHeadcount());
    }

    // ================================================================
    // calculateSurplusDeficit
    // ================================================================

    public function test_calculateSurplusDeficit_surplus(): void
    {
        $project = $this->makeProject();
        $this->addRequirementToProject($project, 'php', 2, 1);

        $m1 = $this->makeMember('m1');
        $this->addSkillToMember($m1, 'php', 3);
        $m2 = $this->makeMember('m2');
        $this->addSkillToMember($m2, 'php', 4);

        $allocations = [
            $this->makeAllocation('m1', 'project-1', 'php', 50),
            $this->makeAllocation('m2', 'project-1', 'php', 50),
        ];

        $result = $this->service->calculateSurplusDeficit($project, $allocations, [$m1, $m2], $this->referenceDate());

        $this->assertSame(1, $result->entries()[0]->gap());
        $this->assertFalse($result->hasDeficit());
    }

    public function test_calculateSurplusDeficit_deficit(): void
    {
        $project = $this->makeProject();
        $this->addRequirementToProject($project, 'php', 3, 3);

        $m1 = $this->makeMember('m1');
        $this->addSkillToMember($m1, 'php', 3);

        $allocations = [
            $this->makeAllocation('m1', 'project-1', 'php', 50),
        ];

        $result = $this->service->calculateSurplusDeficit($project, $allocations, [$m1], $this->referenceDate());

        $this->assertSame(-2, $result->entries()[0]->gap());
        $this->assertTrue($result->hasDeficit());
    }

    public function test_calculateSurplusDeficit_wrong_skill_ignored(): void
    {
        $project = $this->makeProject();
        $this->addRequirementToProject($project, 'php', 3, 1);

        $m1 = $this->makeMember('m1');
        $this->addSkillToMember($m1, 'php', 5);

        $allocations = [
            $this->makeAllocation('m1', 'project-1', 'java', 50), // wrong skill
        ];

        $result = $this->service->calculateSurplusDeficit($project, $allocations, [$m1], $this->referenceDate());

        $this->assertSame(0, $result->entries()[0]->qualifiedHeadcount());
    }

    public function test_calculateSurplusDeficit_inactive_allocation_ignored(): void
    {
        $project = $this->makeProject();
        $this->addRequirementToProject($project, 'php', 3, 1);

        $m1 = $this->makeMember('m1');
        $this->addSkillToMember($m1, 'php', 5);

        $allocations = [
            $this->makeRevokedAllocation('m1', 'project-1', 'php', 50),
        ];

        $result = $this->service->calculateSurplusDeficit($project, $allocations, [$m1], $this->referenceDate());

        $this->assertSame(0, $result->entries()[0]->qualifiedHeadcount());
    }

    public function test_calculateSurplusDeficit_below_proficiency_not_qualified(): void
    {
        $project = $this->makeProject();
        $this->addRequirementToProject($project, 'php', 3, 1);

        $m1 = $this->makeMember('m1');
        $this->addSkillToMember($m1, 'php', 2);

        $allocations = [
            $this->makeAllocation('m1', 'project-1', 'php', 50),
        ];

        $result = $this->service->calculateSurplusDeficit($project, $allocations, [$m1], $this->referenceDate());

        $this->assertSame(0, $result->entries()[0]->qualifiedHeadcount());
    }

    public function test_calculateSurplusDeficit_member_not_in_list_ignored(): void
    {
        $project = $this->makeProject();
        $this->addRequirementToProject($project, 'php', 3, 1);

        // allocation references m1 but member list is empty
        $allocations = [
            $this->makeAllocation('m1', 'project-1', 'php', 50),
        ];

        $result = $this->service->calculateSurplusDeficit($project, $allocations, [], $this->referenceDate());

        $this->assertSame(0, $result->entries()[0]->qualifiedHeadcount());
    }

    public function test_calculateSurplusDeficit_no_allocations(): void
    {
        $project = $this->makeProject();
        $this->addRequirementToProject($project, 'php', 3, 2);

        $result = $this->service->calculateSurplusDeficit($project, [], [], $this->referenceDate());

        $this->assertSame(-2, $result->entries()[0]->gap());
    }

    // ================================================================
    // detectOverload
    // ================================================================

    public function test_detectOverload_no_overload_at_100_percent(): void
    {
        $member = $this->makeMember('m1', 8.0);
        $allocations = [
            $this->makeAllocation('m1', 'p1', 'php', 60),
            $this->makeAllocation('m1', 'p2', 'java', 40),
        ];

        $result = $this->service->detectOverload([$member], $allocations, $this->referenceDate());

        $entry = $result->entries()[0];
        $this->assertSame(100, $entry->totalAllocatedPercentage());
        $this->assertFalse($entry->isOverloaded());
        $this->assertSame(0.0, $entry->overloadHours());
    }

    public function test_detectOverload_at_120_percent(): void
    {
        $member = $this->makeMember('m1', 8.0);
        $allocations = [
            $this->makeAllocation('m1', 'p1', 'php', 60),
            $this->makeAllocation('m1', 'p2', 'java', 60),
        ];

        $result = $this->service->detectOverload([$member], $allocations, $this->referenceDate());

        $entry = $result->entries()[0];
        $this->assertSame(120, $entry->totalAllocatedPercentage());
        $this->assertTrue($entry->isOverloaded());
        $this->assertEqualsWithDelta(1.6, $entry->overloadHours(), 0.001);
    }

    public function test_detectOverload_no_allocations(): void
    {
        $member = $this->makeMember('m1');

        $result = $this->service->detectOverload([$member], [], $this->referenceDate());

        $entry = $result->entries()[0];
        $this->assertSame(0, $entry->totalAllocatedPercentage());
        $this->assertSame(0.0, $entry->overloadHours());
    }

    public function test_detectOverload_custom_working_hours(): void
    {
        $member = $this->makeMember('m1', 6.0);
        $allocations = [
            $this->makeAllocation('m1', 'p1', 'php', 100),
            $this->makeAllocation('m1', 'p2', 'java', 50),
        ];

        $result = $this->service->detectOverload([$member], $allocations, $this->referenceDate());

        $entry = $result->entries()[0];
        $this->assertSame(150, $entry->totalAllocatedPercentage());
        $this->assertEqualsWithDelta(3.0, $entry->overloadHours(), 0.001);
    }

    public function test_detectOverload_revoked_and_out_of_range_excluded(): void
    {
        $member = $this->makeMember('m1', 8.0);
        $allocations = [
            $this->makeAllocation('m1', 'p1', 'php', 60),
            $this->makeRevokedAllocation('m1', 'p2', 'java', 50),
            $this->makeAllocation('m1', 'p3', 'go', 50, '2024-01-01', '2024-12-31'),
        ];

        $result = $this->service->detectOverload([$member], $allocations, $this->referenceDate());

        $entry = $result->entries()[0];
        $this->assertSame(60, $entry->totalAllocatedPercentage());
        $this->assertFalse($entry->isOverloaded());
    }

    // ================================================================
    // detectSkillGapWarnings
    // ================================================================

    public function test_detectSkillGapWarnings_no_warning_when_meets_requirement(): void
    {
        $project = $this->makeProject();
        $this->addRequirementToProject($project, 'php', 3, 1);

        $member = $this->makeMember('m1');
        $this->addSkillToMember($member, 'php', 3);

        $allocations = [
            $this->makeAllocation('m1', 'project-1', 'php', 50),
        ];

        $warnings = $this->service->detectSkillGapWarnings($project, $allocations, [$member], $this->referenceDate());

        $this->assertEmpty($warnings);
    }

    public function test_detectSkillGapWarnings_insufficient_proficiency(): void
    {
        $project = $this->makeProject();
        $this->addRequirementToProject($project, 'php', 3, 1);

        $member = $this->makeMember('m1');
        $this->addSkillToMember($member, 'php', 2);

        $allocations = [
            $this->makeAllocation('m1', 'project-1', 'php', 50),
        ];

        $warnings = $this->service->detectSkillGapWarnings($project, $allocations, [$member], $this->referenceDate());

        $this->assertCount(1, $warnings);
        $this->assertSame(3, $warnings[0]->requiredLevel());
        $this->assertSame(2, $warnings[0]->actualLevel());
    }

    public function test_detectSkillGapWarnings_member_lacks_skill(): void
    {
        $project = $this->makeProject();
        $this->addRequirementToProject($project, 'php', 3, 1);

        $member = $this->makeMember('m1');
        // no php skill added

        $allocations = [
            $this->makeAllocation('m1', 'project-1', 'php', 50),
        ];

        $warnings = $this->service->detectSkillGapWarnings($project, $allocations, [$member], $this->referenceDate());

        $this->assertCount(1, $warnings);
        $this->assertNull($warnings[0]->actualLevel());
    }

    public function test_detectSkillGapWarnings_inactive_allocation_no_warning(): void
    {
        $project = $this->makeProject();
        $this->addRequirementToProject($project, 'php', 3, 1);

        $member = $this->makeMember('m1');
        $this->addSkillToMember($member, 'php', 1);

        $allocations = [
            $this->makeRevokedAllocation('m1', 'project-1', 'php', 50),
        ];

        $warnings = $this->service->detectSkillGapWarnings($project, $allocations, [$member], $this->referenceDate());

        $this->assertEmpty($warnings);
    }

    public function test_detectSkillGapWarnings_multiple_warnings(): void
    {
        $project = $this->makeProject();
        $this->addRequirementToProject($project, 'php', 3, 1);
        $this->addRequirementToProject($project, 'java', 4, 1);

        $member = $this->makeMember('m1');
        $this->addSkillToMember($member, 'php', 1);
        $this->addSkillToMember($member, 'java', 2);

        $allocations = [
            $this->makeAllocation('m1', 'project-1', 'php', 30),
            $this->makeAllocation('m1', 'project-1', 'java', 30),
        ];

        $warnings = $this->service->detectSkillGapWarnings($project, $allocations, [$member], $this->referenceDate());

        $this->assertCount(2, $warnings);
    }

    // ================================================================
    // buildTeamCapacitySnapshot
    // ================================================================

    public function test_buildTeamCapacitySnapshot_available_percentage(): void
    {
        $member = $this->makeMember('m1');
        $this->addSkillToMember($member, 'php', 3);

        $allocations = [
            $this->makeAllocation('m1', 'p1', 'php', 60),
        ];

        $result = $this->service->buildTeamCapacitySnapshot([$member], $allocations, $this->referenceDate());

        $entry = $result->entries()[0];
        $this->assertSame(40, $entry->availablePercentage());
    }

    public function test_buildTeamCapacitySnapshot_clamped_at_zero(): void
    {
        $member = $this->makeMember('m1');
        $this->addSkillToMember($member, 'php', 3);

        $allocations = [
            $this->makeAllocation('m1', 'p1', 'php', 60),
            $this->makeAllocation('m1', 'p2', 'php', 60),
        ];

        $result = $this->service->buildTeamCapacitySnapshot([$member], $allocations, $this->referenceDate());

        $this->assertSame(0, $result->entries()[0]->availablePercentage());
    }

    public function test_buildTeamCapacitySnapshot_skill_proficiency_map(): void
    {
        $member = $this->makeMember('m1');
        $this->addSkillToMember($member, 'php', 3);
        $this->addSkillToMember($member, 'java', 5);

        $result = $this->service->buildTeamCapacitySnapshot([$member], [], $this->referenceDate());

        $entry = $result->entries()[0];
        $profs = $entry->skillProficiencies();
        $this->assertSame(3, $profs['php']);
        $this->assertSame(5, $profs['java']);
    }

    public function test_buildTeamCapacitySnapshot_null_for_unowned_skill(): void
    {
        $m1 = $this->makeMember('m1');
        $this->addSkillToMember($m1, 'php', 3);

        $m2 = $this->makeMember('m2');
        $this->addSkillToMember($m2, 'java', 4);

        $result = $this->service->buildTeamCapacitySnapshot([$m1, $m2], [], $this->referenceDate());

        $entry1 = $result->findByMemberId(new MemberId('m1'));
        $entry2 = $result->findByMemberId(new MemberId('m2'));

        $this->assertNull($entry1->proficiencyFor(new \App\Domain\Skill\SkillId('java')));
        $this->assertNull($entry2->proficiencyFor(new \App\Domain\Skill\SkillId('php')));
        $this->assertSame(3, $entry1->proficiencyFor(new \App\Domain\Skill\SkillId('php')));
        $this->assertSame(4, $entry2->proficiencyFor(new \App\Domain\Skill\SkillId('java')));
    }
}
