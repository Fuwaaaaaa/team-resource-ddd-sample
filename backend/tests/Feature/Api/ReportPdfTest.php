<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Authorization\UserRole;
use App\Infrastructure\Persistence\Eloquent\Models\AllocationModel;
use App\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use App\Infrastructure\Persistence\Eloquent\Models\MemberSkillModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProjectModel;
use App\Infrastructure\Persistence\Eloquent\Models\RequiredSkillModel;
use App\Infrastructure\Persistence\Eloquent\Models\SkillModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ReportPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_pdf_for_project_status(): void
    {
        $this->actingAs(User::factory()->create());

        $skill = SkillModel::factory()->create(['name' => 'PHP']);
        $project = ProjectModel::factory()->create([
            'name' => 'Orion',
            'status' => 'active',
            'planned_start_date' => '2026-04-01',
            'planned_end_date' => '2026-06-30',
        ]);
        RequiredSkillModel::create([
            'id' => (string) Str::uuid7(),
            'project_id' => $project->id,
            'skill_id' => $skill->id,
            'required_proficiency' => 3,
            'headcount' => 2,
        ]);
        $member = MemberModel::factory()->create(['name' => 'Alice']);
        MemberSkillModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $member->id,
            'skill_id' => $skill->id,
            'proficiency' => 4,
        ]);
        AllocationModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $member->id,
            'project_id' => $project->id,
            'skill_id' => $skill->id,
            'allocation_percentage' => 50,
            'period_start' => '2026-04-01',
            'period_end' => '2026-05-15',
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/reports/projects/{$project->id}/pdf?referenceDate=2026-05-01");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', "attachment; filename=\"project-status-{$project->id}.pdf\"");

        $body = (string) $response->getContent();
        $this->assertNotEmpty($body);
        $this->assertStringStartsWith('%PDF-', $body); // PDF マジックナンバー
        $this->assertGreaterThan(2000, strlen($body)); // ある程度のサイズ
    }

    public function test_returns_404_for_missing_project(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/reports/projects/00000000-0000-0000-0000-000000000000/pdf')
            ->assertStatus(404);
    }

    public function test_validates_reference_date_format(): void
    {
        $this->actingAs(User::factory()->create());
        $project = ProjectModel::factory()->create(['status' => 'active']);

        $this->getJson("/api/reports/projects/{$project->id}/pdf?referenceDate=2026/05/01")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['referenceDate']);
    }

    public function test_viewer_can_download_pdf(): void
    {
        // 既存 CSV export と同じ、読み取り系は viewer でも可
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $this->actingAs($viewer);

        $project = ProjectModel::factory()->create(['status' => 'active']);

        $this->getJson("/api/reports/projects/{$project->id}/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $project = ProjectModel::factory()->create();

        $this->getJson("/api/reports/projects/{$project->id}/pdf")
            ->assertStatus(401);
    }
}
