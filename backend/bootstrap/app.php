<?php

use App\Application\Allocation\Exceptions\AllocationCapacityExceededException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
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
    })->create();
