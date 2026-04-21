<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use App\Infrastructure\Persistence\Eloquent\Models\MemberSkillModel;
use App\Infrastructure\Persistence\Eloquent\Models\SkillModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MemberCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_index_returns_empty_array_on_fresh_database(): void
    {
        $this->getJson('/api/members')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_store_creates_member_with_uuid_v7_id(): void
    {
        $response = $this->postJson('/api/members', [
            'name' => 'New Member',
            'standardWorkingHours' => 7.5,
        ])->assertCreated();

        $id = $response->json('data.id');
        $this->assertNotEmpty($id);
        $this->assertSame('7', $id[14], 'Generated ID must be UUIDv7 (version nibble at char 14)');
        $this->assertSame('New Member', $response->json('data.name'));
        $this->assertEqualsWithDelta(7.5, $response->json('data.standardWorkingHours'), 0.01);
    }

    public function test_index_returns_created_member(): void
    {
        $this->postJson('/api/members', ['name' => 'Alice', 'standardWorkingHours' => 8.0])->assertCreated();
        $this->postJson('/api/members', ['name' => 'Bob', 'standardWorkingHours' => 8.0])->assertCreated();

        $this->getJson('/api/members')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_delete_cascades_member_skills(): void
    {
        $skill = SkillModel::factory()->create();
        $member = MemberModel::factory()->create();
        MemberSkillModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $member->id,
            'skill_id' => $skill->id,
            'proficiency' => 3,
        ]);

        $this->assertSame(1, MemberSkillModel::where('member_id', $member->id)->count());

        $this->deleteJson("/api/members/{$member->id}")->assertNoContent();

        $this->assertSame(0, MemberSkillModel::where('member_id', $member->id)->count());
        $this->assertNull(MemberModel::find($member->id));
    }

    public function test_store_rejects_empty_name(): void
    {
        $this->postJson('/api/members', ['name' => '', 'standardWorkingHours' => 8.0])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }
}
