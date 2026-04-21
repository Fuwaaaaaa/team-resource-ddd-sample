<?php

use App\Http\Controllers\Api\AllocationController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\Dashboard\CapacityController;
use App\Http\Controllers\Api\Dashboard\OverloadController;
use App\Http\Controllers\Api\Dashboard\SkillGapController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\TimelineController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MeController;
use Illuminate\Support\Facades\Route;

/*
 * Sanctum SPA Cookie 認証 + ロールベース認可:
 *   - auth:sanctum   : ログイン済み (admin/manager/viewer)
 *   - role:admin,manager : 書込可能
 *   - role:admin         : 監査ログ閲覧可能
 */

Route::post('/login', [LoginController::class, 'store']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy']);
    Route::get('/me', MeController::class);

    // ===== 読み取り系 (admin / manager / viewer すべて許可) =====

    Route::prefix('dashboard')->group(function (): void {
        Route::get('/capacity', CapacityController::class);
        Route::get('/overload', OverloadController::class);
        Route::get('/skill-gaps', SkillGapController::class);
    });

    Route::get('/skills', [SkillController::class, 'index']);
    Route::get('/members', [MemberController::class, 'index']);
    Route::get('/members/{id}', [MemberController::class, 'show']);
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::get('/allocations', [AllocationController::class, 'index']);
    Route::get('/timeline', TimelineController::class);

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

        Route::post('/allocations', [AllocationController::class, 'store']);
        Route::post('/allocations/{id}/revoke', [AllocationController::class, 'revoke']);
    });

    // ===== 監査ログ (admin のみ) =====

    Route::middleware('role:admin')->group(function (): void {
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
    });
});
