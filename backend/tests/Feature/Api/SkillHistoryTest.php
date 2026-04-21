<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use App\Infrastructure\Persistence\Eloquent\Models\SkillModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SkillHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_returns_empty_when_no_history(): void
    {
        $member = MemberModel::factory()->create();

        $this->getJson("/api/members/{$member->id}/skill-history")
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_records_proficiency_changes_in_order(): void
    {
        $member = MemberModel::factory()->create();
        $skill = SkillModel::factory()->create();

        foreach ([2, 3, 4] as $lvl) {
            $this->putJson("/api/members/{$member->id}/skills/{$skill->id}", [
                'proficiency' => $lvl,
            ])->assertOk();
        }

        $response = $this->getJson("/api/members/{$member->id}/skill-history")->assertOk();
        $data = $response->json('data');
        $this->assertCount(3, $data);
        $this->assertSame([2, 3, 4], array_column($data, 'proficiency'));
        $this->assertSame($skill->id, $data[0]['skillId']);
    }

    public function test_filter_by_skill_id(): void
    {
        $member = MemberModel::factory()->create();
        $skillA = SkillModel::factory()->create();
        $skillB = SkillModel::factory()->create();

        $this->putJson("/api/members/{$member->id}/skills/{$skillA->id}", ['proficiency' => 3])->assertOk();
        $this->putJson("/api/members/{$member->id}/skills/{$skillB->id}", ['proficiency' => 2])->assertOk();

        $response = $this->getJson("/api/members/{$member->id}/skill-history?skillId={$skillA->id}")->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($skillA->id, $data[0]['skillId']);
    }

    public function test_captures_user_name(): void
    {
        $member = MemberModel::factory()->create();
        $skill = SkillModel::factory()->create();

        $this->putJson("/api/members/{$member->id}/skills/{$skill->id}", ['proficiency' => 4])->assertOk();

        $data = $this->getJson("/api/members/{$member->id}/skill-history")->json('data');
        $this->assertNotNull($data[0]['changedBy']);
        $this->assertNotNull($data[0]['changedByName']);
    }

    public function test_validates_bad_period_end(): void
    {
        $member = MemberModel::factory()->create();

        $this->getJson("/api/members/{$member->id}/skill-history?periodStart=2026-05-10&periodEnd=2026-05-01")
            ->assertStatus(422);
    }
}
