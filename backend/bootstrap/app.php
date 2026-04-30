<?php

use App\Application\Admin\Exceptions\CannotChangeOwnRoleException;
use App\Application\Admin\Exceptions\EmailTakenException;
use App\Application\Admin\Exceptions\LastAdminLockException;
use App\Application\Admin\Exceptions\OccConflictException;
use App\Application\Allocation\Exceptions\AllocationCapacityExceededException;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureRole;
use App\Infrastructure\Metrics\MetricsCounter;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // statefulApi() が EnsureFrontendRequestsAreStateful::class を api group の
        // 先頭に追加する。ここで再度 prepend すると二重登録になり、内側 pipeline が
        // 外側終了時に Session を剥がしてしまう (RuntimeException: Session store not set)。
        // AssignRequestId のみ prepend する。
        $middleware->statefulApi();
        $middleware->api(prepend: [
            AssignRequestId::class,
        ]);
        $middleware->web(prepend: [
            AssignRequestId::class,
        ]);
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Domain / Application 例外を HTTP ステータスにマップ
        $exceptions->render(function (AllocationCapacityExceededException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'allocation_capacity_exceeded',
            ], 422);
        });

        // Admin / Authorization domain exceptions → HTTP 422 / 409
        // 各 denial に対し MetricsCounter を 1 ずつ進める (Prometheus 観測のため)。
        $exceptions->render(function (CannotChangeOwnRoleException $e, Request $request) {
            app(MetricsCounter::class)->increment(MetricsCounter::ADMIN_USER_CANNOT_CHANGE_OWN_ROLE);

            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'cannot_change_self',
            ], 422);
        });
        $exceptions->render(function (LastAdminLockException $e, Request $request) {
            app(MetricsCounter::class)->increment(MetricsCounter::ADMIN_USER_LAST_ADMIN_LOCK);

            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'last_admin_lock',
            ], 422);
        });
        $exceptions->render(function (EmailTakenException $e, Request $request) {
            app(MetricsCounter::class)->increment(MetricsCounter::ADMIN_USER_EMAIL_TAKEN);

            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'email_taken',
            ], 422);
        });
        $exceptions->render(function (OccConflictException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'occ_mismatch',
            ], 409);
        });
    })->create();
