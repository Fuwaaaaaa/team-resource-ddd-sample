<?php

declare(strict_types=1);

namespace App\Application\Admin\Commands;

use App\Application\Admin\DTOs\UserDto;
use App\Application\Admin\Exceptions\CannotDisableSelfException;
use App\Application\Admin\Exceptions\LastAdminLockException;
use App\Domain\Authorization\Events\UserDisabled;
use App\Domain\Authorization\UserRole;
use App\Infrastructure\Events\DomainEventDispatcher;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * 退職者対応 / アカウント停止のため admin が user を無効化する。
 *
 *   1. 自身を disable しようとしたら CannotDisableSelfException
 *   2. lockForUpdate で対象 user 行をロック
 *   3. 既に無効化済なら no-op (idempotent — disabled_at は変更しない)
 *   4. 対象が admin ロールのとき、 \"有効な admin 残数\" を FOR UPDATE で集計し
 *      これが 1 (= 自分しかいない) なら LastAdminLockException
 *   5. disabled_at = now()、 sanctum tokens / sessions を全削除 (即時ログアウト)
 *   6. UserDisabled ドメインイベント発火 → audit_logs にも記録される
 */
final class DisableUserHandler
{
    public function __construct(
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    public function handle(DisableUserCommand $command): UserDto
    {
        if ($command->targetUserId === $command->actorUserId) {
            throw new CannotDisableSelfException;
        }

        $events = [];
        $resultUser = DB::transaction(function () use ($command, &$events) {
            $user = User::query()->whereKey($command->targetUserId)->lockForUpdate()->first();
            if ($user === null) {
                throw (new ModelNotFoundException)->setModel(User::class, [$command->targetUserId]);
            }

            // Idempotent: 既に無効化済なら何もしない。 既存 disabled_at は保持。
            if ($user->isDisabled()) {
                return $user;
            }

            // 最後の admin 防壁: 対象が admin かつ \"他に有効な admin がいない\" なら拒否。
            // ChangeUserRoleHandler と同じく aggregate FOR UPDATE は pgsql で禁止 (0A000) のため、
            // 該当 row を pluck() してから count する。 これで他者の同時 disable とも直列化する。
            if ($user->role === UserRole::Admin) {
                $activeAdminCount = User::query()
                    ->where('role', UserRole::Admin->value)
                    ->whereNull('disabled_at')
                    ->lockForUpdate()
                    ->pluck('id')
                    ->count();
                if ($activeAdminCount <= 1) {
                    throw new LastAdminLockException;
                }
            }

            $user->update(['disabled_at' => now()]);

            // 即時ログアウト: API token + DB-backed session を全削除する。
            // 該当 user は次のリクエストで 401 / login 拒否のいずれかになる。
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();

            $events[] = new UserDisabled(
                userId: $user->id,
                disabledByUserId: $command->actorUserId,
            );

            return $user->fresh();
        });

        $this->eventDispatcher->dispatchAll($events);

        return UserDto::fromModel($resultUser);
    }
}
