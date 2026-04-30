<?php

use App\Http\Controllers\Api\AbsenceController;
use App\Http\Controllers\Api\Admin\UsersController as AdminUsersController;
use App\Http\Controllers\Api\AllocationChangeRequestController;
use App\Http\Controllers\Api\AllocationController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\Dashboard\CapacityController;
use App\Http\Controllers\Api\Dashboard\CapacityForecastController;
use App\Http\Controllers\Api\Dashboard\KpiSummaryController;
use App\Http\Controllers\Api\Dashboard\KpiTrendController;
use App\Http\Controllers\Api\Dashboard\OverloadController;
use App\Http\Controllers\Api\Dashboard\SkillGapController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\TimelineController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\MetricsController;
use Illuminate\Support\Facades\Route;

/*
 * Sanctum SPA Cookie 認証 + ロールベース認可:
 *   - auth:sanctum   : ログイン済み (admin/manager/viewer)
 *   - role:admin,manager : 書込可能
 *   - role:admin         : 監査ログ閲覧可能
 */

Route::post('/login', [LoginController::class, 'store']);

// Prometheus / OpenMetrics scrape 用 (sanctum 認証の外)。
// Authorization: Bearer <METRICS_TOKEN> が一致したときのみ 200、 それ以外は 404。
Route::get('/metrics', [MetricsController::class, 'index']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy']);
    Route::get('/me', MeController::class);

    // ===== 読み取り系 (admin / manager / viewer すべて許可) =====

    Route::prefix('dashboard')->group(function (): void {
        Route::get('/capacity', CapacityController::class);
        Route::get('/overload', OverloadController::class);
        Route::get('/skill-gaps', SkillGapController::class);
        Route::get('/kpi-summary', KpiSummaryController::class);
        Route::get('/capacity-forecast', CapacityForecastController::class);
        Route::get('/kpi-trend', KpiTrendController::class);
    });

    Route::get('/skills', [SkillController::class, 'index']);
    Route::get('/members', [MemberController::class, 'index']);
    Route::get('/members/{id}', [MemberController::class, 'show']);
    Route::get('/members/{id}/kpi', [MemberController::class, 'kpi']);
    Route::get('/members/{id}/skill-history', [MemberController::class, 'skillHistory']);
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::get('/projects/{id}/kpi', [ProjectController::class, 'kpi']);
    Route::get('/allocations', [AllocationController::class, 'index']);
    Route::get('/allocations/suggestions', [AllocationController::class, 'suggestions']);
    Route::get('/timeline', TimelineController::class);

    // Allocation change requests (read: 自分の申請を参照可 / admin は全件)
    Route::get('/allocation-requests', [AllocationChangeRequestController::class, 'index']);

    // Absence (read)
    Route::get('/absences', [AbsenceController::class, 'index']);
    Route::get('/members/{memberId}/absences', [AbsenceController::class, 'byMember']);

    // 運用メモ (entity に紐付く comments)
    Route::get('/notes', [NoteController::class, 'index']);
    Route::post('/notes', [NoteController::class, 'store']);
    Route::delete('/notes/{id}', [NoteController::class, 'destroy']);

    // Notifications (current user's inbox)
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    // CSV エクスポート (読み取り系)
    Route::prefix('export')->group(function (): void {
        Route::get('/members', [ExportController::class, 'members']);
        Route::get('/projects', [ExportController::class, 'projects']);
        Route::get('/allocations', [ExportController::class, 'allocations']);
    });

    // PDF レポート (読み取り系)
    Route::get('/reports/projects/{id}/pdf', [ReportController::class, 'projectStatusPdf']);

    // ===== 書込系 (admin / manager のみ) =====

    Route::middleware('role:admin,manager')->group(function (): void {
        Route::post('/members', [MemberController::class, 'store']);
        Route::patch('/members/{id}', [MemberController::class, 'update']);
        Route::delete('/members/{id}', [MemberController::class, 'destroy']);
        Route::put('/members/{id}/skills/{skillId}', [MemberController::class, 'upsertSkill']);

        Route::post('/projects', [ProjectController::class, 'store']);
        Route::patch('/projects/{id}', [ProjectController::class, 'update']);
        Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
        Route::put('/projects/{id}/required-skills/{skillId}', [ProjectController::class, 'upsertRequiredSkill']);
        Route::post('/projects/{id}/status', [ProjectController::class, 'changeStatus']);

        Route::post('/allocations', [AllocationController::class, 'store']);
        Route::post('/allocations/{id}/revoke', [AllocationController::class, 'revoke']);

        // Allocation change request: manager/admin が申請
        Route::post('/allocation-requests', [AllocationChangeRequestController::class, 'store']);

        Route::post('/absences', [AbsenceController::class, 'store']);
        Route::post('/absences/{id}/cancel', [AbsenceController::class, 'cancel']);

        // CSV インポート (admin/manager)
        Route::prefix('import')->group(function (): void {
            Route::post('/members', [ImportController::class, 'members']);
            Route::post('/projects', [ImportController::class, 'projects']);
            Route::post('/allocations', [ImportController::class, 'allocations']);
        });
    });

    // ===== 監査ログ (admin のみ) =====

    Route::middleware('role:admin')->group(function (): void {
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/export/audit-logs', [ExportController::class, 'auditLogs']);

        // Allocation change request: 承認 / 却下 は admin のみ
        Route::post('/allocation-requests/{id}/approve', [AllocationChangeRequestController::class, 'approve']);
        Route::post('/allocation-requests/{id}/reject', [AllocationChangeRequestController::class, 'reject']);

        // Admin user management (Next 26)
        Route::prefix('admin')->group(function (): void {
            Route::get('/users', [AdminUsersController::class, 'index']);
            Route::post('/users', [AdminUsersController::class, 'store']);
            Route::patch('/users/{id}/role', [AdminUsersController::class, 'updateRole'])
                ->whereNumber('id');
            Route::post('/users/{id}/reset-password', [AdminUsersController::class, 'resetPassword'])
                ->whereNumber('id');
            Route::post('/users/{id}/disable', [AdminUsersController::class, 'disable'])
                ->whereNumber('id');
            Route::post('/users/{id}/enable', [AdminUsersController::class, 'enable'])
                ->whereNumber('id');
        });
    });
});
