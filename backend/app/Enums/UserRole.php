<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';      // 全操作 + 監査ログ閲覧
    case Manager = 'manager';  // Members/Projects/Allocations の CRUD
    case Viewer = 'viewer';    // 閲覧のみ

    /** 書込権限があるロール */
    public function canWrite(): bool
    {
        return match ($this) {
            self::Admin, self::Manager => true,
            self::Viewer => false,
        };
    }

    /** 監査ログ閲覧権限 */
    public function canViewAuditLog(): bool
    {
        return $this === self::Admin;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
