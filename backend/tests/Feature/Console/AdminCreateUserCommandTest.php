<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Domain\Authorization\UserRole;
use App\Mail\UserInviteMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class AdminCreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_creates_admin_user_emits_audit_event_and_sends_invite(): void
    {
        $exit = $this->artisan('admin:create-user', [
            '--role' => 'admin',
            '--email' => 'cli-admin@example.com',
            '--name' => 'CLI Admin',
        ])
            ->expectsOutputToContain('User created. Invite email sent.')
            ->expectsOutputToContain('Invite link')
            ->run();

        $this->assertSame(0, $exit);

        $user = User::where('email', 'cli-admin@example.com')->firstOrFail();
        $this->assertSame('CLI Admin', $user->name);
        $this->assertSame(UserRole::Admin, $user->role);
        // 招待 token が発行されている (24h 有効)
        $this->assertNotNull($user->invite_token);
        $this->assertNotNull($user->invite_token_expires_at);

        // UserCreated event is forwarded to audit_logs through the same listener
        // chain that HTTP-driven user creation uses.
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'UserCreated',
            'aggregate_type' => 'user',
        ]);

        // 招待 mail が宛先 user に送られている
        Mail::assertSent(UserInviteMail::class, fn (UserInviteMail $m) => $m->hasTo('cli-admin@example.com'));
    }

    public function test_creates_manager_role(): void
    {
        $exit = $this->artisan('admin:create-user', [
            '--role' => 'manager',
            '--email' => 'cli-mgr@example.com',
            '--name' => 'CLI Manager',
        ])->run();

        $this->assertSame(0, $exit);
        $this->assertSame(UserRole::Manager, User::where('email', 'cli-mgr@example.com')->firstOrFail()->role);
    }

    public function test_json_output_contains_invite_url(): void
    {
        $exit = $this->artisan('admin:create-user', [
            '--role' => 'viewer',
            '--email' => 'cli-json@example.com',
            '--name' => 'CLI Viewer',
            '--json' => true,
        ])->run();

        $this->assertSame(0, $exit);
        $this->assertDatabaseHas('users', ['email' => 'cli-json@example.com']);
        // user は invite_token を持つ
        $this->assertNotNull(User::where('email', 'cli-json@example.com')->firstOrFail()->invite_token);
    }

    public function test_rejects_invalid_role(): void
    {
        $exit = $this->artisan('admin:create-user', [
            '--role' => 'super-admin',
            '--email' => 'rejected@example.com',
            '--name' => 'Rejected',
        ])
            ->expectsOutputToContain('selected role is invalid')
            ->run();

        // Symfony INVALID = 2
        $this->assertSame(2, $exit);
        $this->assertDatabaseMissing('users', ['email' => 'rejected@example.com']);
    }

    public function test_rejects_invalid_email(): void
    {
        $exit = $this->artisan('admin:create-user', [
            '--role' => 'admin',
            '--email' => 'not-an-email',
            '--name' => 'X',
        ])->run();

        $this->assertSame(2, $exit);
        $this->assertDatabaseMissing('users', ['name' => 'X']);
    }

    public function test_rejects_missing_options(): void
    {
        $exit = $this->artisan('admin:create-user')->run();

        $this->assertSame(2, $exit);
    }

    public function test_fails_on_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $exit = $this->artisan('admin:create-user', [
            '--role' => 'admin',
            '--email' => 'taken@example.com',
            '--name' => 'Dup',
        ])
            ->expectsOutputToContain('already in use')
            ->run();

        // Symfony FAILURE = 1
        $this->assertSame(1, $exit);
        // Original user not overwritten
        $this->assertSame(1, User::where('email', 'taken@example.com')->count());
    }
}
