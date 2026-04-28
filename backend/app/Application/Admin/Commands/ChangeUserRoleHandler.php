<?php

declare(strict_types=1);

namespace App\Application\Admin\Commands;

use App\Application\Admin\DTOs\UserDto;
use App\Application\Admin\Exceptions\CannotChangeOwnRoleException;
use App\Application\Admin\Exceptions\LastAdminLockException;
use App\Application\Admin\Exceptions\OccConflictException;
use App\Domain\Authorization\Events\UserRoleChanged;
use App\Enums\UserRole;
use App\Infrastructure\Events\DomainEventDispatcher;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * NOTE: User is an authentication identity, NOT a domain aggregate.
 * Unlike Member/Project/Allocation, we touch Eloquent User directly here.
 * RBAC is an Application Concern (per the Authorization bounded context).
 *
 * Concurrency model:
 *   1. SELECT ... FOR UPDATE on the target user inside a transaction
 *   2. If demoting an admin, also lock SELECT count(*) FROM users WHERE role='admin'
 *      to make the "last admin" check atomic with the UPDATE
 *   3. OCC: WHERE updated_at = expectedUpdatedAt; 0 rows updated → 409 Conflict
 */
final class ChangeUserRoleHandler
{
    public function __construct(
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    /**
     * @return UserDto|null null when the role was unchanged (idempotent no-op).
     */
    public function handle(ChangeUserRoleCommand $command): ?UserDto
    {
        if ($command->targetUserId === $command->actorUserId) {
            throw new CannotChangeOwnRoleException();
        }

        $newRole = UserRole::from($command->newRole);

        $events = [];

        $resultUser = DB::transaction(function () use ($command, $newRole, &$events) {
            $user = User::query()->whereKey($command->targetUserId)->lockForUpdate()->first();
            if ($user === null) {
                throw (new ModelNotFoundException())->setModel(User::class, [$command->targetUserId]);
            }

            $oldRole = $user->role;

            // No-op idempotency: same role → return current user without event/update.
            if ($oldRole === $newRole) {
                return $user;
            }

            // Last-admin lock: demoting the only admin would lock everyone out.
            if ($oldRole === UserRole::Admin && $newRole !== UserRole::Admin) {
                $adminCount = User::query()
                    ->where('role', UserRole::Admin->value)
                    ->lockForUpdate()
                    ->count();
                if ($adminCount <= 1) {
                    throw new LastAdminLockException();
                }
            }

            // OCC: only update if updated_at hasn't drifted since the client read it.
            $expectedAt = (new DateTimeImmutable($command->expectedUpdatedAt))
                ->format('Y-m-d H:i:s');
            $affected = User::query()
                ->whereKey($command->targetUserId)
                ->where('updated_at', $expectedAt)
                ->update(['role' => $newRole->value]);

            if ($affected === 0) {
                throw new OccConflictException();
            }

            $events[] = new UserRoleChanged(
                userId: $user->id,
                from: $oldRole,
                to: $newRole,
                reason: $command->reason,
            );

            return $user->fresh();
        });

        $this->eventDispatcher->dispatchAll($events);

        // null signals "no-op" to the controller (current role == requested role on entry).
        return ($resultUser->role === $newRole && $events === [])
            ? null
            : UserDto::fromModel($resultUser);
    }
}
