<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Admin\Commands\CreateUserCommand;
use App\Application\Admin\Commands\CreateUserHandler;
use App\Application\Admin\Exceptions\EmailTakenException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * CLI からユーザー (admin / manager / viewer) を作成する。
 *
 *   php artisan admin:create-user --role=admin --email=ops@example.com --name='Ops Lead'
 *   php artisan admin:create-user --role=manager --email=mng@example.com --name='Manager A' --json
 *
 * 16 文字のランダムパスワードを生成し、 stdout に **1 度だけ** 表示する。
 * 再表示はできないため、 operator はその場で記録すること。
 *
 * 想定ユースケース:
 *   - 初回デプロイ後の最初の admin (UI 経由は admin がいないと作れない chicken-and-egg)
 *   - 全 admin を失った場合の災害復旧
 *   - staging / dev 環境でのプログラマブルなアカウント生成
 *
 * 実装は {@see CreateUserHandler} を再利用し、 ドメインイベント (UserCreated) と
 * audit_logs への記録は HTTP 経由作成と完全に同じ経路を辿る。
 */
class AdminCreateUserCommand extends Command
{
    protected $signature = 'admin:create-user
        {--role= : admin / manager / viewer}
        {--email= : ユーザーメールアドレス}
        {--name= : 表示名}
        {--json : 結果を JSON で出力 (パスワード含む)}';

    protected $description = 'CLI から admin / manager / viewer ユーザーを作成する';

    public function handle(CreateUserHandler $handler): int
    {
        $role = (string) ($this->option('role') ?? '');
        $email = (string) ($this->option('email') ?? '');
        $name = (string) ($this->option('name') ?? '');

        $validator = Validator::make(
            ['role' => $role, 'email' => $email, 'name' => $name],
            [
                'role' => ['required', 'in:admin,manager,viewer'],
                'email' => ['required', 'email'],
                'name' => ['required', 'string', 'min:1', 'max:255'],
            ],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            $this->newLine();
            $this->line('Usage:');
            $this->line('  php artisan admin:create-user --role=admin --email=ops@example.com --name="Ops Lead"');

            return self::INVALID;
        }

        try {
            $result = $handler->handle(new CreateUserCommand(
                name: $name,
                email: $email,
                role: $role,
            ));
        } catch (EmailTakenException $e) {
            $this->error("Email '{$email}' is already in use.");

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('User created.');
        $this->table(['Field', 'Value'], [
            ['ID', (string) $result->user->id],
            ['Name', $result->user->name],
            ['Email', $result->user->email],
            ['Role', $result->user->role],
        ]);
        $this->newLine();
        $this->warn('Generated password (shown once — record it now):');
        $this->line('  '.$result->generatedPassword);
        $this->newLine();
        $this->comment('User should change this password on first login.');

        return self::SUCCESS;
    }
}
