<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Application\Admin\Commands\ChangeUserRoleCommand;
use App\Application\Admin\Commands\ChangeUserRoleHandler;
use App\Application\Admin\Commands\CreateUserCommand;
use App\Application\Admin\Commands\CreateUserHandler;
use App\Application\Admin\Commands\ResetUserPasswordCommand;
use App\Application\Admin\Commands\ResetUserPasswordHandler;
use App\Application\Admin\DTOs\UserDto;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin-only user management endpoints. All routes are protected by
 * middleware('role:admin') in routes/api.php; this controller assumes
 * that gate is in place.
 */
class UsersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:120',
            'perPage' => 'nullable|integer|min:1|max:200',
        ]);

        $query = User::query()->orderByDesc('created_at');

        if ($search = (string) $request->query('search', '')) {
            // Postgres LIKE is case-sensitive; SQLite/MySQL LIKE is not.
            // Lowercase both sides so admins searching "Alice" find "alice@…"
            // regardless of driver.
            $needle = mb_strtolower($search).'%';
            $query->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$needle]);
            });
        }

        $paginator = $query->paginate((int) $request->query('perPage', 50));

        return response()->json([
            'data' => array_map(
                fn (User $u) => UserDto::fromModel($u)->toArray(),
                $paginator->items(),
            ),
            'meta' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request, CreateUserHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $dto = $handler->handle(new CreateUserCommand(
            name: $validated['name'],
            email: $validated['email'],
            role: $validated['role'],
        ));

        return response()
            ->json($dto->toArray(), 201)
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    public function updateRole(Request $request, int $id, ChangeUserRoleHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
            'reason' => 'required|string|min:1|max:200',
            'expectedUpdatedAt' => 'required|string',
        ]);

        $actor = $request->user();
        if ($actor === null) {
            // Should never trigger thanks to auth:sanctum middleware, but
            // defensive — fail closed rather than smuggle a null actorUserId.
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $dto = $handler->handle(new ChangeUserRoleCommand(
            targetUserId: $id,
            actorUserId: (int) $actor->id,
            newRole: $validated['role'],
            reason: $validated['reason'],
            expectedUpdatedAt: $validated['expectedUpdatedAt'],
        ));

        // null DTO == no-op (idempotent same-role); return current user state for client refresh.
        if ($dto === null) {
            $current = User::query()->findOrFail($id);

            return response()->json(['user' => UserDto::fromModel($current)->toArray()]);
        }

        return response()->json(['user' => $dto->toArray()]);
    }

    public function resetPassword(Request $request, int $id, ResetUserPasswordHandler $handler): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Existence check up front so we map to 404 cleanly (handler also re-checks under lock).
        if (! User::query()->whereKey($id)->exists()) {
            throw (new ModelNotFoundException)->setModel(User::class, [$id]);
        }

        $dto = $handler->handle(new ResetUserPasswordCommand(
            targetUserId: $id,
            actorUserId: (int) $actor->id,
        ));

        return response()
            ->json($dto->toArray())
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
