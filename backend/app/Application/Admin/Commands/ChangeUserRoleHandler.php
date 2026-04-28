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
use DateTimeZone;
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
            throw new CannotChangeOwnRoleException;
        }

        $newRole = UserRole::from($command->newRole);

        $events = [];

        $resultUser = DB::transaction(function () use ($command, $newRole, &$events) {
            $user = User::query()->whereKey($command->targetUserId)->lockForUpdate()->first();
            if ($user === null) {
                throw (new ModelNotFoundException)->setModel(User::class, [$command->targetUserId]);
            }

            $oldRole = $user->role;

            // OCC: even no-op requests must surface staleness. A client whose
            // expectedUpdatedAt is older than the current row likely missed an
            // intervening change → return 409 so they refetch and re-decide.
            $expectedAtForCheck = (new DateTimeImmutable($command->expectedUpdatedAt))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
            if ($user->updated_at->utc()->format('Y-m-d H:i:s') !== $expectedAtForCheck) {
                throw new OccConflictException;
            }

            // No-op idempotency: same role → no event, no update, but the OCC
            // check above already validated freshness.
            if ($oldRole === $newRole) {
                return $user;
            }

            // Last-admin lock: demoting the only admin would lock everyone out.
            //
            // PostgreSQL forbids `FOR UPDATE` with aggregate functions
            // (SQLSTATE 0A000), so we cannot do `count()->lockForUpdate()`.
            // Instead we SELECT all admin rows FOR UPDATE (no aggregate, just
            // a row lock on the set we care about) and count the resulting
            // collection in PHP. This locks the same rows the count was meant
            // to gate against, so two concurrent admins demoting each other
            // serialize: the second sees count=1 after the first commits.
            if ($oldRole === UserRole::Admin && $newRole !== UserRole::Admin) {
                $adminCount = User::query()
                    ->where('role', UserRole::Admin->value)
                    ->lockForUpdate()
                    ->pluck('id')
                    ->count();
                if ($adminCount <= 1) {
                    throw new LastAdminLockException;
                }
            }

            // OCC was already validated above before the no-op short-circuit.
            // The lockForUpdate on the target row serializes concurrent writers,
            // so a plain UPDATE is sufficient here.
            $user->update(['role' => $newRole->value]);

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
