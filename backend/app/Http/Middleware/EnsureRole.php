<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * route middleware: role:admin,manager のように使う。
 * 認証済みユーザーの role が指定リストに含まれない場合 403。
 */
final class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if ($user === null) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        foreach ($roles as $role) {
            $expected = UserRole::tryFrom($role);
            if ($expected !== null && $user->role === $expected) {
                return $next($request);
            }
        }

        throw new AccessDeniedHttpException(
            sprintf('Role %s is not permitted here.', $user->role?->value ?? 'unknown'),
        );
    }
}
