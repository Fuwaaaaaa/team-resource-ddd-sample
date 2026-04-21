<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Service;

use App\Domain\Project\ProjectId;
use App\Domain\Skill\SkillId;
use App\Infrastructure\Service\AllocationService;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\AllocationTestFactory;

final class AllocationServiceSuggestionsTest extends TestCase
{
    use AllocationTestFactory;

    private AllocationService $service;

    protected function setUp(): void
    {
        $this->service = new AllocationService;
    }

    public function test_excludes_members_without_required_proficiency(): void
    {
        $m1 = $this->makeMember('m1');
        $this->addSkillToMember($m1, 'php', 2); // 要求 3 未満

        $result = $this->service->suggestCandidates(
            new SkillId('php'),
            3,
            new ProjectId('p1'),
            $this->referenceDate(),
            [$m1],
            [],
        );

        $this->assertEmpty($result);
    }

    public function test_excludes_members_with_no_capacity(): void
    {
        $m1 = $this->makeMember('m1');
        $this->addSkillToMember($m1, 'php', 3);
        $allocations = [
            $this->makeAllocation('m1', 'other-project', 'php', 100),
        ];

        $result = $this->service->suggestCandidates(
            new SkillId('php'),
            3,
            new ProjectId('p1'),
            $this->referenceDate(),
            [$m1],
            $allocations,
        );

        $this->assertEmpty($result);
    }

    public function test_orders_by_score_descending(): void
    {
        $m1 = $this->makeMember('m1');
        $this->addSkillToMember($m1, 'php', 5); // 大きな proficiency 余裕
        $m2 = $this->makeMember('m2');
        $this->addSkillToMember($m2, 'php', 3);
        $allocations = [
            $this->makeAllocation('m1', 'other', 'php', 80), // 空き 20%
            $this->makeAllocation('m2', 'other', 'php', 20), // 空き 80%
        ];

        $result = $this->service->suggestCandidates(
            new SkillId('php'),
            3,
            new ProjectId('p1'),
            $this->referenceDate(),
            [$m1, $m2],
            $allocations,
        );

        $this->assertCount(2, $result);
        // m2 はキャパ 80 + 熟練度余裕 0 → 80
        // m1 はキャパ 20 + 熟練度余裕 20 → 40
        $this->assertSame('m2', $result[0]->memberId()->toString());
        $this->assertSame('m1', $result[1]->memberId()->toString());
    }

    public function test_same_project_experience_boosts_score(): void
    {
        $m1 = $this->makeMember('m1');
        $this->addSkillToMember($m1, 'php', 3);
        $m2 = $this->makeMember('m2');
        $this->addSkillToMember($m2, 'php', 3);
        $allocations = [
            // m2 は p1 プロジェクト経験あり (revoked でもカウント)
            $this->makeRevokedAllocation('m2', 'p1', 'php', 40),
        ];

        $result = $this->service->suggestCandidates(
            new SkillId('php'),
            3,
            new ProjectId('p1'),
            $this->referenceDate(),
            [$m1, $m2],
            $allocations,
        );

        $this->assertCount(2, $result);
        // m2 の方が experience_bonus 分スコアが高い
        $this->assertSame('m2', $result[0]->memberId()->toString());
        $this->assertSame(1, $result[0]->pastProjectExperienceCount());
    }

    public function test_reasons_describe_selection(): void
    {
        $m1 = $this->makeMember('m1');
        $this->addSkillToMember($m1, 'php', 4);

        $result = $this->service->suggestCandidates(
            new SkillId('php'),
            3,
            new ProjectId('p1'),
            $this->referenceDate(),
            [$m1],
            [],
        );

        $this->assertCount(1, $result);
        $reasons = $result[0]->reasons();
        $this->assertStringContainsString('L4', $reasons[0]);
        $this->assertStringContainsString('100%', $reasons[1]);
    }

    public function test_limit_caps_results(): void
    {
        $members = [];
        for ($i = 1; $i <= 10; $i++) {
            $m = $this->makeMember("m{$i}");
            $this->addSkillToMember($m, 'php', 3);
            $members[] = $m;
        }

        $result = $this->service->suggestCandidates(
            new SkillId('php'),
            3,
            new ProjectId('p1'),
            $this->referenceDate(),
            $members,
            [],
            3,
        );

        $this->assertCount(3, $result);
    }
}
