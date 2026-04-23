<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\UserRole;
use App\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProjectModel;
use App\Infrastructure\Persistence\Eloquent\Models\SkillModel;
use App\Mail\NotificationMail;
use App\Models\User;
use App\Notifications\NotificationSeverity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * 通知チャネル (email / slack) のゲート条件を検証。
 *
 * in-app 通知 (notifications テーブル) はここでは検証しない
 * (既存 AllocationChangeRequestTest 等でカバー済み)。
 *
 * イベントソースは AllocationChangeRequestSubmitted (severity=warning) を使い、
 * `POST /api/allocation-requests` を叩くことで発火させる。
 */
final class NotificationChannelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_severity_at_least_ordering(): void
    {
        $this->assertTrue(NotificationSeverity::Critical->atLeast(NotificationSeverity::Warning));
        $this->assertTrue(NotificationSeverity::Warning->atLeast(NotificationSeverity::Warning));
        $this->assertFalse(NotificationSeverity::Info->atLeast(NotificationSeverity::Warning));
    }

    // ---------- email ----------

    public function test_email_is_sent_when_enabled_and_severity_above_threshold(): void
    {
        config([
            'notifications.email.enabled' => true,
            'notifications.email.min_severity' => 'warning',
        ]);
        Mail::fake();
        Http::fake(); // slack 側を no-op に

        // 受信者として admin を 2 人, manager 1 人, viewer 1 人 (配信除外確認)
        User::factory()->count(2)->create(['role' => UserRole::Admin]);
        User::factory()->create(['role' => UserRole::Manager]);
        User::factory()->create(['role' => UserRole::Viewer]);

        $submitter = User::factory()->create(['role' => UserRole::Manager]);
        $this->actingAs($submitter);

        $this->postJson('/api/allocation-requests', $this->validCreatePayload())
            ->assertCreated();

        // submitter 含む admin 2 + manager 2 = 4 通
        Mail::assertSent(NotificationMail::class, 4);
    }

    public function test_email_is_not_sent_when_disabled(): void
    {
        config([
            'notifications.email.enabled' => false,
            'notifications.slack.webhook_url' => '',
        ]);
        Mail::fake();
        Http::fake();

        User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));

        $this->postJson('/api/allocation-requests', $this->validCreatePayload())->assertCreated();

        Mail::assertNothingSent();
    }

    public function test_email_skipped_when_severity_below_threshold(): void
    {
        config([
            'notifications.email.enabled' => true,
            'notifications.email.min_severity' => 'critical', // Submitted=warning なのでスキップ
        ]);
        Mail::fake();
        Http::fake();

        User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));

        $this->postJson('/api/allocation-requests', $this->validCreatePayload())->assertCreated();

        Mail::assertNothingSent();
    }

    // ---------- slack ----------

    public function test_slack_webhook_is_posted_when_url_set(): void
    {
        config([
            'notifications.email.enabled' => false,
            'notifications.slack.webhook_url' => 'https://hooks.slack.com/services/TEST/XXX',
            'notifications.slack.min_severity' => 'warning',
        ]);
        Mail::fake();
        Http::fake([
            'hooks.slack.com/*' => Http::response('ok', 200),
        ]);

        User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));

        $this->postJson('/api/allocation-requests', $this->validCreatePayload())->assertCreated();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'hooks.slack.com')
                && str_contains((string) $request->body(), 'AllocationChangeRequestSubmitted');
        });
    }

    public function test_slack_skipped_when_url_empty(): void
    {
        config([
            'notifications.email.enabled' => false,
            'notifications.slack.webhook_url' => '',
        ]);
        Http::fake();

        User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));

        $this->postJson('/api/allocation-requests', $this->validCreatePayload())->assertCreated();

        Http::assertNothingSent();
    }

    public function test_slack_skipped_when_severity_below_threshold(): void
    {
        config([
            'notifications.email.enabled' => false,
            'notifications.slack.webhook_url' => 'https://hooks.slack.com/services/TEST/XXX',
            'notifications.slack.min_severity' => 'critical', // Submitted=warning → skip
        ]);
        Http::fake();

        User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));

        $this->postJson('/api/allocation-requests', $this->validCreatePayload())->assertCreated();

        Http::assertNothingSent();
    }

    public function test_slack_webhook_failure_does_not_break_request(): void
    {
        config([
            'notifications.email.enabled' => false,
            'notifications.slack.webhook_url' => 'https://hooks.slack.com/services/TEST/XXX',
            'notifications.slack.min_severity' => 'warning',
        ]);
        Http::fake([
            'hooks.slack.com/*' => Http::response('nope', 500),
        ]);

        User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));

        // slack が 500 でもリクエスト本体は 201 で成功すること (ドメイン処理を止めない)
        $this->postJson('/api/allocation-requests', $this->validCreatePayload())
            ->assertCreated();
    }

    // ===== helpers =====

    /** @return array<string, mixed> */
    private function validCreatePayload(): array
    {
        $skill = SkillModel::factory()->create();
        $project = ProjectModel::factory()->create(['status' => 'active']);
        $member = MemberModel::factory()->create();

        return [
            'type' => 'create_allocation',
            'payload' => [
                'memberId' => $member->id,
                'projectId' => $project->id,
                'skillId' => $skill->id,
                'allocationPercentage' => 30,
                'periodStart' => '2026-05-01',
                'periodEnd' => '2026-05-31',
            ],
            'reason' => 'test',
        ];
    }
}
