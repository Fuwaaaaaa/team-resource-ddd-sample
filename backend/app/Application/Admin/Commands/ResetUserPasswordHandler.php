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

        $resultUser = DB::transaction(function () use ($command, $generatedPassword) {
            $user = User::query()->whereKey($command->targetUserId)->lockForUpdate()->first();
            if ($user === null) {
                throw (new ModelNotFoundException())->setModel(User::class, [$command->targetUserId]);
            }

            $user->update(['password' => Hash::make($generatedPassword)]);

            // Sanctum API tokens — invalidate all
            $user->tokens()->delete();

            // Database session driver — wipe active sessions for this user
            DB::table('sessions')->where('user_id', $command->targetUserId)->delete();

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
