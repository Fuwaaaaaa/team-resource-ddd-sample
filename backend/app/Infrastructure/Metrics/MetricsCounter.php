<?php

declare(strict_types=1);

namespace App\Infrastructure\Metrics;

use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Prometheus 用の denial / failure イベントカウンタ。
 *
 * 「成功イベント」 (UserCreated 等) は audit_logs に行が積まれるので
 * MetricsController がそのまま COUNT(*) で出せる。 一方 \"成功 \"せずに
 * 投げられる例外 (EmailTaken / LastAdminLock / CannotChangeOwnRole 等) は
 * audit_logs に残らないので、 例外 render パスでこの service を呼んで
 * Cache に counter を積む。
 *
 * Cache backend は config('cache.default') に従う。 production = database,
 * test = array (in-memory)。 MAX_TTL_DAYS で過去 30 日にローテートされる
 * — Prometheus は monotonic counter を期待するため厳密にはリセットしない
 * 方が望ましいが、 cache が爆発する方が悪影響なので妥協。 30 日は十分長い
 * (Prometheus retention はそれ未満が一般的)。
 */
final class MetricsCounter
{
    /** Cache key prefix。 衝突防止 + bulk clear 用 */
    private const PREFIX = 'metrics:counter:';

    /** Counter の TTL (秒)。 30 日 = 2,592,000 */
    private const TTL_SECONDS = 30 * 24 * 60 * 60;

    /** 既知のカウンタ名。 enumerate 用に明示しておく */
    public const ADMIN_USER_EMAIL_TAKEN = 'admin_user_email_taken_total';

    public const ADMIN_USER_LAST_ADMIN_LOCK = 'admin_user_last_admin_lock_total';

    public const ADMIN_USER_CANNOT_CHANGE_OWN_ROLE = 'admin_user_cannot_change_own_role_total';

    /** @return string[] 既知カウンタ名一覧 */
    public static function knownCounters(): array
    {
        return [
            self::ADMIN_USER_EMAIL_TAKEN,
            self::ADMIN_USER_LAST_ADMIN_LOCK,
            self::ADMIN_USER_CANNOT_CHANGE_OWN_ROLE,
        ];
    }

    public function __construct(private Cache $cache) {}

    public function increment(string $name): void
    {
        $key = self::PREFIX.$name;
        if (! $this->cache->has($key)) {
            // 初回: TTL 付きで 0 を入れて、 直後に increment する
            $this->cache->put($key, 0, self::TTL_SECONDS);
        }
        $this->cache->increment($key);
    }

    public function get(string $name): int
    {
        return (int) ($this->cache->get(self::PREFIX.$name) ?? 0);
    }
}
