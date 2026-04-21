<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class NoteTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_store_creates_note_with_author(): void
    {
        $member = MemberModel::factory()->create();

        $response = $this->postJson('/api/notes', [
            'entityType' => 'member',
            'entityId' => $member->id,
            'body' => '育成目的でこの配置にした',
        ])->assertCreated();

        $this->assertSame('育成目的でこの配置にした', $response->json('data.body'));
        $this->assertSame($this->user->id, $response->json('data.author_id'));
        $this->assertSame($this->user->name, $response->json('data.author.name'));
    }

    public function test_index_scoped_to_entity(): void
    {
        $member = MemberModel::factory()->create();
        $other = MemberModel::factory()->create();

        $this->postJson('/api/notes', [
            'entityType' => 'member', 'entityId' => $member->id, 'body' => 'A',
        ])->assertCreated();
        $this->postJson('/api/notes', [
            'entityType' => 'member', 'entityId' => $other->id, 'body' => 'B',
        ])->assertCreated();

        $response = $this->getJson("/api/notes?entityType=member&entityId={$member->id}")->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('A', $response->json('data.0.body'));
    }

    public function test_store_validates_body_max(): void
    {
        $member = MemberModel::factory()->create();
        $this->postJson('/api/notes', [
            'entityType' => 'member',
            'entityId' => $member->id,
            'body' => str_repeat('x', 2001),
        ])->assertStatus(422);
    }

    public function test_store_rejects_bad_entity_type(): void
    {
        $member = MemberModel::factory()->create();
        $this->postJson('/api/notes', [
            'entityType' => 'skill',
            'entityId' => $member->id,
            'body' => 'x',
        ])->assertStatus(422);
    }

    public function test_author_can_delete_own_note(): void
    {
        $member = MemberModel::factory()->create();
        $created = $this->postJson('/api/notes', [
            'entityType' => 'member', 'entityId' => $member->id, 'body' => 'mine',
        ])->assertCreated();
        $noteId = $created->json('data.id');

        $this->deleteJson("/api/notes/{$noteId}")->assertNoContent();
        $this->assertNull(Note::find($noteId));
    }

    public function test_non_admin_cannot_delete_others_note(): void
    {
        $member = MemberModel::factory()->create();
        $other = User::factory()->create(['role' => 'manager']);
        $n = Note::create([
            'id' => (string) Str::uuid7(),
            'entity_type' => 'member',
            'entity_id' => $member->id,
            'author_id' => $other->id,
            'body' => 'other',
        ]);

        // setUp の $this->user は admin デフォルト（UserFactory）— 削除可能になってしまう
        // 代わりに manager で再ログイン
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager);

        $this->deleteJson("/api/notes/{$n->id}")->assertForbidden();
    }

    public function test_admin_can_delete_others_note(): void
    {
        $member = MemberModel::factory()->create();
        $other = User::factory()->create(['role' => 'manager']);
        $n = Note::create([
            'id' => (string) Str::uuid7(),
            'entity_type' => 'member',
            'entity_id' => $member->id,
            'author_id' => $other->id,
            'body' => 'other',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->deleteJson("/api/notes/{$n->id}")->assertNoContent();
    }
}
