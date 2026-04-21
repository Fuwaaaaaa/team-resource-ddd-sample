<?php

declare(strict_types=1);

namespace App\Domain\Project;

/**
 * プロジェクトのライフサイクル状態。
 *
 *   planning  : 計画中（要求スキル編集中、アロケーション未開始）
 *   active    : 稼働中（アロケーション可能、ダッシュボードに反映）
 *   completed : 完了（正常終了。アロケーション解除済み、KPI 計算対象から除外）
 *   canceled  : 中止（異常終了。completed と同様に計算対象外）
 *
 * 許可される遷移:
 *   planning  → active, canceled
 *   active    → completed, canceled
 *   completed → (terminal)
 *   canceled  → (terminal)
 */
enum ProjectStatus: string
{
    case Planning = 'planning';
    case Active = 'active';
    case Completed = 'completed';
    case Canceled = 'canceled';

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Canceled;
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /** キャパシティ / スキルギャップ計算の対象とするか */
    public function countsForCapacity(): bool
    {
        return $this === self::Planning || $this === self::Active;
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Planning => $next === self::Active || $next === self::Canceled,
            self::Active => $next === self::Completed || $next === self::Canceled,
            self::Completed, self::Canceled => false,
        };
    }
}
