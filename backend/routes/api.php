<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MeController;
use Illuminate\Support\Facades\Route;

/*
 * Sanctum SPA Cookie 認証:
 *   1. GET  /sanctum/csrf-cookie  (フレームワーク標準、XSRF-TOKEN を発行)
 *   2. POST /login                 (セッション発行)
 *   3. API 呼び出しは credentials:'include' で同一ドメイン cookie を送る
 *   4. POST /logout                (セッション破棄)
 */

Route::post('/login', [LoginController::class, 'store']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy']);
    Route::get('/me', MeController::class);
});
