<?php

declare(strict_types=1);

namespace App\Application\Admin\Commands;

use App\Application\Admin\DTOs\CreatedUserDto;
use App\Application\Admin\DTOs\UserDto;
use App\Application\Admin\Exceptions\EmailTakenException;
use App\Domain\Authorization\Events\UserCreated;
use App\Domain\Authorization\UserRole;
use App\Infrastructure\Events\DomainEventDispatcher;
use App\Mail\UserInviteMail;
use App\Models\User;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * NOTE: User is an authentication identity, NOT a domain aggregate.
 * Unlike Member/Project/Allocation, we touch Eloquent User directly here.
 * RBAC is an Application Concern (per the Authorization bounded context).
 *
 * 招待リンクフロー (TODO-3) 移行後:
 *   1. password は不可推測のランダム値で初期化される (誰もこの状態でログインできない)
 *   2. invite_token を発行し、 24 時間有効にする
 *   3. 招待 email を送信 (UserInviteMail) する
 *   4. UserCreated ドメインイベントを発火 (audit_logs に記録)
 *
 * 実 password は招待を accept したときに本人が設定する (AcceptInviteHandler 参照)。
 */
final class CreateUserHandler
{
    /** 招待リンクの有効時間 */
    private const INVITE_TTL_HOURS = 24;

    public function __construct(
        private DomainEventDispatcher $eventDispatcher,
        private Mailer $mailer,
    ) {}

    public function handle(CreateUserCommand $command): CreatedUserDto
    {
        $role = UserRole::from($command->role);
        $inviteToken = bin2hex(random_bytes(32));
        $expiresAt = now()->addHours(self::INVITE_TTL_HOURS);

        // 重複 email を INSERT より先に検出する。
        // pgsql は失敗した INSERT が外側 transaction (RefreshDatabase) を 25P02 で
        // 汚染し、 後続の SELECT が "current transaction is aborted" で全て落ちる。
        // 同 email の同時 INSERT 競合は下の catch でカバーする。
        if (User::where('email', $command->email)->exists()) {
            throw new EmailTakenException($command->email);
        }

        try {
            $user = User::create([
                'name' => $command->name,
                'email' => $command->email,
                // accept されるまで誰もログインできないよう、 推測不能なランダム値を入れる。
                // accept 時に本人選択の password で上書きされる。
                'password' => Hash::make(Str::random(64)),
                'role' => $role,
                'invite_token' => $inviteToken,
                'invite_token_expires_at' => $expiresAt,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // pre-check と INSERT の間にレースで第二者が同 email を入れた場合の救済。
            throw new EmailTakenException($command->email);
        } catch (QueryException $e) {
            // 旧 driver / wrapper が UniqueConstraintViolationException に正規化されない fallback。
            if ($e->getCode() === '23505' || str_contains((string) $e->getMessage(), 'users_email_unique')) {
                throw new EmailTakenException($command->email);
            }
            throw $e;
        }

        $inviteUrl = rtrim((string) config('app.url'), '/').'/invite/'.$inviteToken;

        $this->mailer->to($user->email)->send(
            new UserInviteMail(
                userName: $user->name,
                role: $role->value,
                inviteUrl: $inviteUrl,
                expiresAt: $expiresAt->toIso8601String(),
            ),
        );

        $this->eventDispatcher->dispatchAll([
            new UserCreated(
                userId: $user->id,
                email: $user->email,
                role: $role,
            ),
        ]);

        return new CreatedUserDto(
            user: UserDto::fromModel($user->fresh()),
            inviteSentTo: $user->email,
            inviteExpiresAt: $expiresAt->toIso8601String(),
            inviteUrl: $inviteUrl,
        );
    }
}
