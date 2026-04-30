<?php

declare(strict_types=1);

namespace App\Application\Admin\Commands;

use App\Application\Admin\DTOs\PasswordResetResultDto;
use App\Application\Admin\DTOs\UserDto;
use App\Domain\Authorization\Events\UserPasswordReset;
use App\Infrastructure\Events\DomainEventDispatcher;
use App\Mail\UserInviteMail;
use App\Models\User;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 招待リンク再発行フロー (TODO-22) 移行後:
 *   1. 既存 password を不可推測のランダム値で上書き (= 旧 password で誰もログインできなくなる)
 *   2. 24 時間有効な invite_token を発行
 *   3. UserInviteMail を送信 (CreateUserHandler と同じ Mail を再利用)
 *   4. UserPasswordReset ドメインイベントを発火 (audit_logs / metrics の semantic を温存)
 *
 * 単一 transaction 内の副作用:
 *   - users.password / invite_token / invite_token_expires_at 更新
 *   - 全 Sanctum personal access token 削除
 *   - DB session driver 上の active session 全削除
 *
 * 自分自身を reset したときは requiresRelogin=true を返し、 frontend が招待メール
 * 受信を促すために /login へ誘導する。
 */
final class ResetUserPasswordHandler
{
    /** 招待リンクの有効時間 (CreateUserHandler と揃える) */
    private const INVITE_TTL_HOURS = 24;

    public function __construct(
        private DomainEventDispatcher $eventDispatcher,
        private Mailer $mailer,
    ) {}

    public function handle(ResetUserPasswordCommand $command): PasswordResetResultDto
    {
        $isSelf = $command->targetUserId === $command->actorUserId;
        $inviteToken = bin2hex(random_bytes(32));
        $expiresAt = now()->addHours(self::INVITE_TTL_HOURS);

        // 旧 password を一発で無効化するための値。
        // bcrypt は 50-300ms 掛かるので、 SELECT ... FOR UPDATE で行を抑える前に算出する
        // (transaction を不必要に長く保持しない)。
        $invalidatedPasswordHash = Hash::make(Str::random(64));

        // session.table が customize されている deployment でも sweep し損ねないよう
        // config から resolve。 default は 'sessions'。
        $sessionsTable = (string) config('session.table', 'sessions');

        $resultUser = DB::transaction(function () use ($command, $invalidatedPasswordHash, $inviteToken, $expiresAt, $sessionsTable) {
            $user = User::query()->whereKey($command->targetUserId)->lockForUpdate()->first();
            if ($user === null) {
                throw (new ModelNotFoundException)->setModel(User::class, [$command->targetUserId]);
            }

            $user->update([
                'password' => $invalidatedPasswordHash,
                'invite_token' => $inviteToken,
                'invite_token_expires_at' => $expiresAt,
            ]);

            // Sanctum API tokens — 全失効
            $user->tokens()->delete();

            // DB session driver — 当該 user の active session を一掃 (file/redis では no-op)
            DB::table($sessionsTable)->where('user_id', $command->targetUserId)->delete();

            return $user->fresh();
        });

        $inviteUrl = rtrim((string) config('app.url'), '/').'/invite/'.$inviteToken;

        $this->mailer->to($resultUser->email)->send(
            new UserInviteMail(
                userName: $resultUser->name,
                role: $resultUser->role->value,
                inviteUrl: $inviteUrl,
                expiresAt: $expiresAt->toIso8601String(),
            ),
        );

        $this->eventDispatcher->dispatchAll([
            new UserPasswordReset(userId: $resultUser->id),
        ]);

        return new PasswordResetResultDto(
            user: UserDto::fromModel($resultUser),
            inviteUrl: $inviteUrl,
            inviteExpiresAt: $expiresAt->toIso8601String(),
            requiresRelogin: $isSelf,
        );
    }
}
