<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProjectModel;
use App\Infrastructure\Persistence\Eloquent\Models\SkillModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_imports_valid_members_csv(): void
    {
        $csv = "name,standard_working_hours\nAlice,8.0\nBob,7.5\nCharlie,6.0\n";
        $file = UploadedFile::fake()->createWithContent('members.csv', $csv);

        $response = $this->post('/api/import/members', ['file' => $file])->assertOk();

        $this->assertSame(3, $response->json('data.imported'));
        $this->assertSame(0, $response->json('data.failureCount'));
        $this->assertSame(3, MemberModel::count());
    }

    public function test_reports_failed_rows_but_imports_good_ones(): void
    {
        // 2 行目: 空名前 (失敗) / 3 行目: hours 超過 (失敗) / 4 行目: 正常
        $csv = "name,standard_working_hours\n,8\nAlice,30\nBob,8\n";
        $file = UploadedFile::fake()->createWithContent('members.csv', $csv);

        $response = $this->post('/api/import/members', ['file' => $file])->assertOk();

        $this->assertSame(1, $response->json('data.imported'));
        $this->assertSame(2, $response->json('data.failureCount'));
        $this->assertSame('Bob', MemberModel::first()->name);
    }

    public function test_handles_utf8_bom(): void
    {
        $csv = "\xEF\xBB\xBFname\nAlice\n";
        $file = UploadedFile::fake()->createWithContent('members.csv', $csv);

        $response = $this->post('/api/import/members', ['file' => $file])->assertOk();
        $this->assertSame(1, $response->json('data.imported'));
    }

    public function test_imports_projects_csv(): void
    {
        $csv = "name\nProject A\nProject B\n";
        $file = UploadedFile::fake()->createWithContent('projects.csv', $csv);

        $response = $this->post('/api/import/projects', ['file' => $file])->assertOk();
        $this->assertSame(2, $response->json('data.imported'));
        $this->assertSame(2, ProjectModel::count());
    }

    public function test_imports_allocations_csv(): void
    {
        $member = MemberModel::factory()->create();
        $project = ProjectModel::factory()->create();
        $skill = SkillModel::factory()->create();

        $csv = "member_id,project_id,skill_id,percentage,start_date,end_date\n"
            ."{$member->id},{$project->id},{$skill->id},50,2026-05-01,2026-05-31\n";
        $file = UploadedFile::fake()->createWithContent('allocations.csv', $csv);

        $response = $this->post('/api/import/allocations', ['file' => $file])->assertOk();
        $this->assertSame(1, $response->json('data.imported'));
    }

    public function test_allocation_import_rejects_over_capacity_row(): void
    {
        $member = MemberModel::factory()->create();
        $project = ProjectModel::factory()->create();
        $skill = SkillModel::factory()->create();

        // 1 件目で 80% 使い、2 件目で 30% 追加 → 容量超過
        $csv = "member_id,project_id,skill_id,percentage,start_date,end_date\n"
            ."{$member->id},{$project->id},{$skill->id},80,2026-05-01,2026-05-31\n"
            ."{$member->id},{$project->id},{$skill->id},30,2026-05-01,2026-05-31\n";
        $file = UploadedFile::fake()->createWithContent('allocations.csv', $csv);

        $response = $this->post('/api/import/allocations', ['file' => $file])->assertOk();
        $this->assertSame(1, $response->json('data.imported'));
        $this->assertSame(1, $response->json('data.failureCount'));
    }

    public function test_viewer_cannot_import(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->actingAs($viewer);

        $file = UploadedFile::fake()->createWithContent('members.csv', "name\nAlice\n");
        $this->post('/api/import/members', ['file' => $file])->assertForbidden();
    }

    public function test_rejects_missing_file(): void
    {
        $this->postJson('/api/import/members', [])->assertStatus(422);
    }
}
