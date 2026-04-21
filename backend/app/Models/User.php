<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
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
