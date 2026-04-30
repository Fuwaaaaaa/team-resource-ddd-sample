<?php

declare(strict_types=1);

namespace App\Application\Admin\Commands;

use App\Application\Admin\DTOs\CreatedUserDto;
use App\Application\Admin\DTOs\UserDto;
use App\Application\Admin\Exceptions\EmailTakenException;
use App\Domain\Authorization\Events\UserCreated;
use App\Domain\Authorization\UserRole;
use App\Infrastructure\Events\DomainEventDispatcher;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * NOTE: User is an authentication identity, NOT a domain aggregate.
 * Unlike Member/Project/Allocation, we touch Eloquent User directly here.
 * RBAC is an Application Concern (per the Authorization bounded context).
 */
final class CreateUserHandler
{
    public function __construct(
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    public function handle(CreateUserCommand $command): CreatedUserDto
    {
        $role = UserRole::from($command->role);
        $generatedPassword = Str::random(16);

        // 重複 email を INSERT より先に検出する。
        // pgsql は失敗した INSERT が外側 transaction (RefreshDatabase) を 25P02 で
        // 汚染し、 後続の SELECT が "current transaction is aborted" で全て落ちる。
        // try/catch でも EmailTakenException 自体は返せるが、 pgsql 経路のテストが
        // この後 query を走らせると壊れるため、 fast-path で先に弾いておく。
        // 同 email の同時 INSERT 競合は下の catch でカバーする。
        if (User::where('email', $command->email)->exists()) {
            throw new EmailTakenException($command->email);
        }

        try {
            $user = User::create([
                'name' => $command->name,
                'email' => $command->email,
                'password' => Hash::make($generatedPassword), // hashed via Eloquent cast as well; explicit for clarity
                'role' => $role,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Laravel が pgsql 23505 / sqlite 23000+UNIQUE / mysql 1062 等を統一して
            // この型に変換してくれる。 driver 別の SQLSTATE / message パターンに依存せずに
            // email 重複だけを EmailTakenException に再分類できる。
            // ※ users.email 以外の unique 制約 (将来追加された場合) は素通しすべきだが
            //    現状 users テーブルの unique 制約は email のみ。 増えたらこの分岐に索引名 check を加える。
            throw new EmailTakenException($command->email);
        } catch (QueryException $e) {
            // 旧 driver / wrapper が UniqueConstraintViolationException に正規化されない
            // 場合のためのフォールバック。 pgsql 23505 と PostgreSQL 標準のメッセージを両方見る。
            if ($e->getCode() === '23505' || str_contains((string) $e->getMessage(), 'users_email_unique')) {
                throw new EmailTakenException($command->email);
            }
            throw $e;
        }

        $this->eventDispatcher->dispatchAll([
            new UserCreated(
                userId: $user->id,
                email: $user->email,
                role: $role,
            ),
        ]);

        return new CreatedUserDto(
            user: UserDto::fromModel($user->fresh()),
            generatedPassword: $generatedPassword,
        );
    }
}
