<?php

declare(strict_types=1);

namespace App\Application\Admin\Commands;

use App\Application\Admin\DTOs\CreatedUserDto;
use App\Application\Admin\DTOs\UserDto;
use App\Application\Admin\Exceptions\EmailTakenException;
use App\Domain\Authorization\Events\UserCreated;
use App\Enums\UserRole;
use App\Infrastructure\Events\DomainEventDispatcher;
use App\Models\User;
use Illuminate\Database\QueryException;
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

        try {
            $user = User::create([
                'name' => $command->name,
                'email' => $command->email,
                'password' => Hash::make($generatedPassword), // hashed via Eloquent cast as well; explicit for clarity
                'role' => $role,
            ]);
        } catch (QueryException $e) {
            // PostgreSQL unique_violation = SQLSTATE 23505. Mapped here for race rephrase.
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
