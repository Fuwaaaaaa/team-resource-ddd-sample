<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Infrastructure\Metrics\MetricsCounter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Prometheus / OpenMetrics 互換のメトリクス公開エンドポイント。
 *
 *   GET /api/metrics         → 404 (METRICS_TOKEN 未設定 or token 不一致)
 *   GET /api/metrics  (with Authorization: Bearer <METRICS_TOKEN>)
 *                            → 200 text/plain; version=0.0.4
 *
 * 露出メトリクス:
 *   admin_user_created_total           — audit_logs から COUNT
 *   admin_user_role_changed_total      — 同上
 *   admin_user_password_reset_total    — 同上
 *   admin_user_email_taken_total       — Cache カウンタ (denial)
 *   admin_user_last_admin_lock_total   — 同上
 *   admin_user_cannot_change_own_role_total — 同上
 *
 * 認証は Sanctum 配下に置かない。 Prometheus scraper は cookie を持たないため、
 * env で発行した bearer token で認可する。 token 不一致 / 未設定はいずれも 404
 * (ルート存在を露呈しないため)。
 */
class MetricsController extends Controller
{
    public function __construct(private MetricsCounter $counter) {}

    public function index(Request $request): Response
    {
        $expected = (string) config('metrics.token');
        if ($expected === '' || $request->bearerToken() !== $expected) {
            // 認可失敗時は 404。 401/403 だと「ルートはあるが認証が要る」 と教えてしまう。
            return response('Not Found', 404);
        }

        $auditCounts = DB::table('audit_logs')
            ->where('aggregate_type', 'user')
            ->select('event_type', DB::raw('count(*) as c'))
            ->groupBy('event_type')
            ->pluck('c', 'event_type')
            ->all();

        $body = $this->renderProm([
            [
                'name' => 'admin_user_created_total',
                'help' => 'Number of admin-driven user creations (UserCreated events in audit_logs).',
                'value' => (int) ($auditCounts['UserCreated'] ?? 0),
            ],
            [
                'name' => 'admin_user_role_changed_total',
                'help' => 'Number of admin-driven role changes (UserRoleChanged events in audit_logs).',
                'value' => (int) ($auditCounts['UserRoleChanged'] ?? 0),
            ],
            [
                'name' => 'admin_user_password_reset_total',
                'help' => 'Number of admin-driven password resets (UserPasswordReset events in audit_logs).',
                'value' => (int) ($auditCounts['UserPasswordReset'] ?? 0),
            ],
            [
                'name' => MetricsCounter::ADMIN_USER_EMAIL_TAKEN,
                'help' => 'Number of denied admin user creates because the email was already in use.',
                'value' => $this->counter->get(MetricsCounter::ADMIN_USER_EMAIL_TAKEN),
            ],
            [
                'name' => MetricsCounter::ADMIN_USER_LAST_ADMIN_LOCK,
                'help' => 'Number of admin operations blocked by the last-admin guard (potential mis-op or attack).',
                'value' => $this->counter->get(MetricsCounter::ADMIN_USER_LAST_ADMIN_LOCK),
            ],
            [
                'name' => MetricsCounter::ADMIN_USER_CANNOT_CHANGE_OWN_ROLE,
                'help' => 'Number of admin operations blocked because they would change the actor\'s own role.',
                'value' => $this->counter->get(MetricsCounter::ADMIN_USER_CANNOT_CHANGE_OWN_ROLE),
            ],
        ]);

        return response($body, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * @param  array<int, array{name: string, help: string, value: int}>  $metrics
     */
    private function renderProm(array $metrics): string
    {
        $lines = [];
        foreach ($metrics as $m) {
            $lines[] = '# HELP '.$m['name'].' '.$m['help'];
            $lines[] = '# TYPE '.$m['name'].' counter';
            $lines[] = $m['name'].' '.$m['value'];
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
