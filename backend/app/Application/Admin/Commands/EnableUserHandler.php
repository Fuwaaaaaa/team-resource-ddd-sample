<?php

declare(strict_types=1);

namespace App\Application\Admin\Commands;

use App\Application\Admin\DTOs\UserDto;
use App\Domain\Authorization\Events\UserEnabled;
use App\Infrastructure\Events\DomainEventDispatcher;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * 無効化された user を再有効化する (disabled_at を null に戻す)。
 * 既に有効な user に対しては no-op (idempotent)。
 */
final class EnableUserHandler
{
    public function __construct(
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    public function handle(EnableUserCommand $command): UserDto
    {
        $events = [];
        $resultUser = DB::transaction(function () use ($command, &$events) {
            $user = User::query()->whereKey($command->targetUserId)->lockForUpdate()->first();
            if ($user === null) {
                throw (new ModelNotFoundException)->setModel(User::class, [$command->targetUserId]);
            }

            // Idempotent: 既に有効なら no-op。 disabled_at は元から null。
            if (! $user->isDisabled()) {
                return $user;
            }

            $user->update(['disabled_at' => null]);

            $events[] = new UserEnabled(
                userId: $user->id,
                enabledByUserId: $command->actorUserId,
            );

            return $user->fresh();
        });

        $this->eventDispatcher->dispatchAll($events);

        return UserDto::fromModel($resultUser);
    }
}
