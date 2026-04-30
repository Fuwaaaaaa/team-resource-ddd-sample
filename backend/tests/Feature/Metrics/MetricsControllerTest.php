<?php

declare(strict_types=1);

namespace Tests\Feature\Metrics;

use App\Application\Admin\Exceptions\EmailTakenException;
use App\Application\Admin\Exceptions\LastAdminLockException;
use App\Infrastructure\Metrics\MetricsCounter;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MetricsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_404_when_token_not_configured(): void
    {
        config(['metrics.token' => '']);

        $this->get('/api/metrics')->assertStatus(404);
    }

    public function test_returns_404_when_token_mismatched(): void
    {
        config(['metrics.token' => 'real-token']);

        $this->get('/api/metrics', ['Authorization' => 'Bearer wrong-token'])
            ->assertStatus(404);
    }

    public function test_returns_404_when_authorization_header_missing(): void
    {
        config(['metrics.token' => 'real-token']);

        $this->get('/api/metrics')->assertStatus(404);
    }

    public function test_returns_prometheus_format_on_valid_token(): void
    {
        config(['metrics.token' => 'real-token']);

        $response = $this->get('/api/metrics', ['Authorization' => 'Bearer real-token']);

        $response->assertStatus(200);
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));

        $body = $response->getContent();
        $this->assertStringContainsString('# HELP admin_user_created_total', $body);
        $this->assertStringContainsString('# TYPE admin_user_created_total counter', $body);
        $this->assertStringContainsString('admin_user_created_total 0', $body);
        $this->assertStringContainsString('admin_user_role_changed_total 0', $body);
        $this->assertStringContainsString('admin_user_password_reset_total 0', $body);
        $this->assertStringContainsString('admin_user_email_taken_total 0', $body);
        $this->assertStringContainsString('admin_user_last_admin_lock_total 0', $body);
        $this->assertStringContainsString('admin_user_cannot_change_own_role_total 0', $body);
    }

    public function test_audit_log_counts_reflected_in_output(): void
    {
        config(['metrics.token' => 'real-token']);

        // 3 件 UserCreated, 2 件 UserRoleChanged, 1 件 UserPasswordReset を直接挿入
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (range(1, 3) as $_) {
            AuditLog::create([
                'id' => (string) Str::uuid7(),
                'user_id' => $admin->id,
                'event_type' => 'UserCreated',
                'aggregate_type' => 'user',
                'aggregate_id' => (string) Str::uuid7(),
                'payload' => [],
                'created_at' => now(),
            ]);
        }
        foreach (range(1, 2) as $_) {
            AuditLog::create([
                'id' => (string) Str::uuid7(),
                'user_id' => $admin->id,
                'event_type' => 'UserRoleChanged',
                'aggregate_type' => 'user',
                'aggregate_id' => (string) Str::uuid7(),
                'payload' => [],
                'created_at' => now(),
            ]);
        }
        AuditLog::create([
            'id' => (string) Str::uuid7(),
            'user_id' => $admin->id,
            'event_type' => 'UserPasswordReset',
            'aggregate_type' => 'user',
            'aggregate_id' => (string) Str::uuid7(),
            'payload' => [],
            'created_at' => now(),
        ]);
        // ノイズ: 別 aggregate_type は countに含まれないことの担保
        AuditLog::create([
            'id' => (string) Str::uuid7(),
            'user_id' => $admin->id,
            'event_type' => 'AllocationCreated',
            'aggregate_type' => 'allocation',
            'aggregate_id' => (string) Str::uuid7(),
            'payload' => [],
            'created_at' => now(),
        ]);

        $body = $this->get('/api/metrics', ['Authorization' => 'Bearer real-token'])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('admin_user_created_total 3', $body);
        $this->assertStringContainsString('admin_user_role_changed_total 2', $body);
        $this->assertStringContainsString('admin_user_password_reset_total 1', $body);
    }

    public function test_denial_exception_render_increments_counter(): void
    {
        // EmailTakenException は通常 controller の unique:users,email validator
        // に先に弾かれるため、 race condition 経路でしか発火しない。 ここでは
        // bootstrap/app.php の render() フックが Counter を進めることを直接 exercise する。
        config(['metrics.token' => 'real-token']);

        $handler = app(ExceptionHandler::class);
        $request = Request::create('/api/admin/users', 'POST');

        // 2 回 EmailTakenException を render → カウンタが 2 になる想定
        $handler->render($request, new EmailTakenException('a@example.com'));
        $handler->render($request, new EmailTakenException('b@example.com'));

        // LastAdminLock も 1 件
        $handler->render($request, new LastAdminLockException);

        $body = $this->get('/api/metrics', ['Authorization' => 'Bearer real-token'])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('admin_user_email_taken_total 2', $body);
        $this->assertStringContainsString('admin_user_last_admin_lock_total 1', $body);
        $this->assertStringContainsString('admin_user_cannot_change_own_role_total 0', $body);
    }

    public function test_metrics_route_is_public_no_sanctum_required(): void
    {
        config(['metrics.token' => 'real-token']);

        // 認証 cookie 無しで Authorization header だけで通る
        $this->get('/api/metrics', ['Authorization' => 'Bearer real-token'])
            ->assertOk();
    }

    public function test_counter_helper_increments_via_cache(): void
    {
        $counter = app(MetricsCounter::class);
        $this->assertSame(0, $counter->get(MetricsCounter::ADMIN_USER_LAST_ADMIN_LOCK));

        $counter->increment(MetricsCounter::ADMIN_USER_LAST_ADMIN_LOCK);
        $counter->increment(MetricsCounter::ADMIN_USER_LAST_ADMIN_LOCK);
        $counter->increment(MetricsCounter::ADMIN_USER_LAST_ADMIN_LOCK);

        $this->assertSame(3, $counter->get(MetricsCounter::ADMIN_USER_LAST_ADMIN_LOCK));
    }
}
