<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Authorization\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'disabled_at',
        'invite_token',
        'invite_token_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'invite_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'disabled_at' => 'datetime',
            'invite_token_expires_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    public function hasRole(UserRole ...$roles): bool
    {
        foreach ($roles as $role) {
            if ($this->role === $role) {
                return true;
            }
        }

        return false;
    }

    public function canWrite(): bool
    {
        return $this->role instanceof UserRole && $this->role->canWrite();
    }

    public function canViewAuditLog(): bool
    {
        return $this->role instanceof UserRole && $this->role->canViewAuditLog();
    }
}
