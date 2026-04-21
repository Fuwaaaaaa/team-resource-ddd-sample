<?php

use App\Http\Controllers\Api\AllocationController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\Dashboard\CapacityController;
use App\Http\Controllers\Api\Dashboard\OverloadController;
use App\Http\Controllers\Api\Dashboard\SkillGapController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MeController;
use Illuminate\Support\Facades\Route;

/*
 * Sanctum SPA Cookie 認証:
 *   1. GET  /sanctum/csrf-cookie   (フレームワーク標準、XSRF-TOKEN を発行)
 *   2. POST /api/login             (セッション発行)
 *   3. API 呼び出しは credentials:'include' で同一ドメイン cookie を送る
 *   4. POST /api/logout            (セッション破棄)
 */

Route::post('/login', [LoginController::class, 'store']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy']);
    Route::get('/me', MeController::class);

    // Dashboard (Query)
    Route::prefix('dashboard')->group(function (): void {
        Route::get('/capacity', CapacityController::class);
        Route::get('/overload', OverloadController::class);
        Route::get('/skill-gaps', SkillGapController::class);
    });

    // Skills (read-only)
    Route::get('/skills', [SkillController::class, 'index']);

    // Members (CRUD + スキル upsert)
    Route::get('/members', [MemberController::class, 'index']);
    Route::post('/members', [MemberController::class, 'store']);
    Route::get('/members/{id}', [MemberController::class, 'show']);
    Route::patch('/members/{id}', [MemberController::class, 'update']);
    Route::delete('/members/{id}', [MemberController::class, 'destroy']);
    Route::put('/members/{id}/skills/{skillId}', [MemberController::class, 'upsertSkill']);

    // Projects (CRUD + 要求スキル upsert)
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::patch('/projects/{id}', [ProjectController::class, 'update']);
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
    Route::put('/projects/{id}/required-skills/{skillId}', [ProjectController::class, 'upsertRequiredSkill']);

    // Allocations (作成 / 失効)
    Route::get('/allocations', [AllocationController::class, 'index']);
    Route::post('/allocations', [AllocationController::class, 'store']);
    Route::post('/allocations/{id}/revoke', [AllocationController::class, 'revoke']);

    // Audit logs (参照のみ)
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
});
