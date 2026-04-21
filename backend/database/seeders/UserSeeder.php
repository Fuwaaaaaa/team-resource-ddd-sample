<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'password' => 'password', 'role' => UserRole::Admin->value],
        );
        User::updateOrCreate(
            ['email' => 'manager@example.com'],
            ['name' => 'Manager', 'password' => 'password', 'role' => UserRole::Manager->value],
        );
        User::updateOrCreate(
            ['email' => 'viewer@example.com'],
            ['name' => 'Viewer', 'password' => 'password', 'role' => UserRole::Viewer->value],
        );
    }
}
