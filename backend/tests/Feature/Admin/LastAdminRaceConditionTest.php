<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Application\Admin\Commands\DisableUserCommand;
use App\Application\Admin\Commands\DisableUserHandler;
use App\Application\Admin\Exceptions\LastAdminLockException;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Throwable;

/**
 * 「最後の admin」 race condition の **真の並行テスト** (TODO-12)。
 *
 * 既存の Feature test は logical 検証のみ — シングルプロセス内で
 * \"`if (count <= 1)` 分岐が走った\" ことしか確認できない。 本テストは
 * pcntl_fork で 2 つの実プロセスを生成して、 同時に別 admin を disable
 * 試行する。 期待される serialization:
 *
 *   t+0ms  : parent forks child A (target = admin α) と child B (target = admin β)
 *   t+50ms : 両 child が rendezvous (microtime barrier) で同時起動
 *            → child A: SELECT FOR UPDATE on α → 取得
 *            → child B: SELECT FOR UPDATE on β → 取得 (別行なので並列)
 *   t+60ms : 両 child が `WHERE role='admin' FOR UPDATE` を発行
 *            → 結果セットは [α, β] (PK 順)。 同じロック取得順序で deadlock 回避。
 *            → child A が先に [α, β] のロックを取り、 child B は queue
 *   t+70ms : child A: count = 2 → α を disable → COMMIT → ロック解放
 *   t+80ms : child B が [α, β] のロック取得 → count = 1 (α は今や disabled)
 *            → LastAdminLockException
 *
 * よって最終結果: 1 件成功 / 1 件 LastAdminLock。 active admin = 1 (β)。
 *
 * 前提:
 *   - SQLite には real row-level lock が無く lockForUpdate() は no-op になる
 *     (sqlite は単一書き込み serialization)。 → pgsql でしか走らない。
 *   - pcntl は Windows / 一部の PHP build に無い。
 *
 * `RefreshDatabase` trait は使えない (test 全体を 1 つの transaction で囲むため、
 * children との分離不能)。 setUp / tearDown で手動クリーンアップする。
 */
final class LastAdminRaceConditionTest extends TestCase
{
    /** 各 test 開始前にこの prefix で残ったデータを掃除するためのマーカ */
    private const TEST_EMAIL_PREFIX = 'race-test-';

    protected function setUp(): void
    {
        // skip 判定を parent::setUp() より先に行う。 Laravel の app boot は
        // 不可視な error/exception handler を多数登録し、 PHPUnit はそれを
        // 「test が leak した handler」 と risky 判定する。 skip するなら
        // app そのものを起動しない方がクリーン。
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension not available (Windows or unsupported PHP build)');
        }
        // DB driver は phpunit.xml で sqlite に固定されている (=テスト環境)。
        // pgsql で動かしたいときは DB_CONNECTION=pgsql を環境変数で override する。
        if (env('DB_CONNECTION', 'sqlite') === 'sqlite') {
            $this->markTestSkipped('SQLite has no real row-level locking; lockForUpdate is a no-op');
        }

        parent::setUp();
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        if ($this->app !== null) {
            $this->cleanupTestData();
        }
        parent::tearDown();
    }

    public function test_concurrent_disable_of_two_admins_serializes_to_one_success_one_lock(): void
    {
        // Setup: 2 active admin users。 互いに相手を disable しに行く構成。
        $admin1 = User::create([
            'name' => 'Race A',
            'email' => self::TEST_EMAIL_PREFIX.'a@example.com',
            'password' => Hash::make('test-password-123'),
            'role' => 'admin',
        ]);
        $admin2 = User::create([
            'name' => 'Race B',
            'email' => self::TEST_EMAIL_PREFIX.'b@example.com',
            'password' => Hash::make('test-password-123'),
            'role' => 'admin',
        ]);
        // disable 操作実行者 (それぞれ admin1 を admin2 から、 admin2 を admin1 から disable する)
        // self-check に当たらないように互い違いの actor を渡す。

        $resultsFile = tempnam(sys_get_temp_dir(), 'race_');
        $goAt = microtime(true) + 0.5; // 500ms 後を rendezvous 時刻にする

        $jobs = [
            ['target' => $admin1->id, 'actor' => $admin2->id, 'tag' => 'A'],
            ['target' => $admin2->id, 'actor' => $admin1->id, 'tag' => 'B'],
        ];

        $children = [];
        foreach ($jobs as $job) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->fail('pcntl_fork failed');
            }
            if ($pid === 0) {
                // Child process
                $outcome = $this->runChild($job, $goAt);
                file_put_contents($resultsFile, $outcome.PHP_EOL, FILE_APPEND);
                exit(0);
            }
            $children[] = $pid;
        }

        // Parent: wait for both children
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $rawResults = (string) file_get_contents($resultsFile);
        @unlink($resultsFile);

        // Each line is "TAG:OUTCOME". 順序は不定。
        $lines = array_filter(explode(PHP_EOL, trim($rawResults)));
        $outcomes = array_map(fn (string $l) => explode(':', $l, 2)[1] ?? '', $lines);

        $this->assertCount(2, $outcomes, "Expected 2 outcomes, got: {$rawResults}");

        $okCount = count(array_filter($outcomes, fn ($o) => $o === 'OK'));
        $lockCount = count(array_filter($outcomes, fn ($o) => $o === 'LAST_ADMIN_LOCK'));
        $errCount = count(array_filter($outcomes, fn (string $o) => str_starts_with($o, 'ERR:')));

        $this->assertSame(0, $errCount, "Unexpected errors: {$rawResults}");
        $this->assertSame(1, $okCount, "Expected exactly 1 success, results: {$rawResults}");
        $this->assertSame(1, $lockCount, "Expected exactly 1 LastAdminLockException, results: {$rawResults}");

        // Final DB state: exactly one admin remains active
        $activeAdminCount = User::query()
            ->where('role', 'admin')
            ->whereNull('disabled_at')
            ->where('email', 'like', self::TEST_EMAIL_PREFIX.'%')
            ->count();
        $this->assertSame(
            1,
            $activeAdminCount,
            'Expected exactly 1 active admin to remain after the race; got '.$activeAdminCount,
        );
    }

    /**
     * @param  array{target: int, actor: int, tag: string}  $job
     */
    private function runChild(array $job, float $goAt): string
    {
        // Children inherit the parent's PDO handle, but PDO is NOT fork-safe.
        // Force a fresh connection in this process before any query.
        DB::purge();
        DB::reconnect();

        // Spin until the rendezvous time so both children start within ~1ms.
        $now = microtime(true);
        if ($now < $goAt) {
            $sleepUs = (int) (($goAt - $now) * 1_000_000);
            usleep(max(0, $sleepUs));
        }

        try {
            $handler = app(DisableUserHandler::class);
            $handler->handle(new DisableUserCommand(
                targetUserId: $job['target'],
                actorUserId: $job['actor'],
            ));

            return $job['tag'].':OK';
        } catch (LastAdminLockException $e) {
            return $job['tag'].':LAST_ADMIN_LOCK';
        } catch (Throwable $e) {
            return $job['tag'].':ERR:'.get_class($e).':'.$e->getMessage();
        }
    }

    private function cleanupTestData(): void
    {
        // 子プロセスが残した行を確実に消す。 RefreshDatabase が使えないので手動。
        // audit_logs 経由 → users (FK 順)。
        $userIds = User::query()
            ->where('email', 'like', self::TEST_EMAIL_PREFIX.'%')
            ->pluck('id');

        if ($userIds->isNotEmpty()) {
            AuditLog::query()->whereIn('user_id', $userIds)->delete();
            User::query()->whereIn('id', $userIds)->delete();
        }
    }
}
