<?php

declare(strict_types=1);

namespace App\Application\Admin\Commands;

use App\Application\Admin\DTOs\PasswordResetResultDto;
use App\Application\Admin\DTOs\UserDto;
use App\Domain\Authorization\Events\UserPasswordReset;
use App\Infrastructure\Events\DomainEventDispatcher;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * NOTE: User is an authentication identity, NOT a domain aggregate.
 * Unlike Member/Project/Allocation, we touch Eloquent User directly here.
 * RBAC is an Application Concern (per the Authorization bounded context).
 *
 * Side effects (all in a single transaction):
 *   - Replaces the password hash
 *   - Deletes ALL Sanctum personal access tokens for the target user
 *   - Deletes ALL active database sessions for the target user
 *
 * If the actor is resetting their own password, requiresRelogin=true so the
 * frontend can redirect to /login after showing the new password.
 */
final class ResetUserPasswordHandler
{
    public function __construct(
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    public function handle(ResetUserPasswordCommand $command): PasswordResetResultDto
    {
        $generatedPassword = Str::random(16);
        $isSelf = $command->targetUserId === $command->actorUserId;

        // Hash bcrypt outside the transaction — at default cost it takes
        // 50-300ms, holding SELECT ... FOR UPDATE on the user row that long
        // would queue every concurrent admin action against the same user.
        $hashedPassword = Hash::make($generatedPassword);

        // Resolve the sessions table from config (defaults to 'sessions');
        // a deployment that customizes session.table would otherwise leave
        // stale sessions alive — silently breaking the "all sessions
        // invalidated" guarantee.
        $sessionsTable = (string) config('session.table', 'sessions');

        $resultUser = DB::transaction(function () use ($command, $hashedPassword, $sessionsTable) {
            $user = User::query()->whereKey($command->targetUserId)->lockForUpdate()->first();
            if ($user === null) {
                throw (new ModelNotFoundException())->setModel(User::class, [$command->targetUserId]);
            }

            $user->update(['password' => $hashedPassword]);

            // Sanctum API tokens — invalidate all (HasApiTokens trait on User)
            $user->tokens()->delete();

            // Database session driver — wipe active sessions for this user.
            // No-op for file/redis drivers; document so callers know.
            DB::table($sessionsTable)->where('user_id', $command->targetUserId)->delete();

            return $user->fresh();
        });

        $this->eventDispatcher->dispatchAll([
            new UserPasswordReset(userId: $resultUser->id),
        ]);

        return new PasswordResetResultDto(
            user: UserDto::fromModel($resultUser),
            generatedPassword: $generatedPassword,
            requiresRelogin: $isSelf,
        );
    }
}
