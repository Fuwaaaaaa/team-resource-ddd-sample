<?php

declare(strict_types=1);

namespace App\Application\Admin\DTOs;

/**
 * admin による既存 user の password reset レスポンス。
 *
 * 招待リンク再発行フロー (TODO-22) 移行後、 admin に password は渡らない。
 * 代わりに UserInviteMail が送信され、 受信者本人が password を再設定する。
 * inviteUrl は dev / staging で SMTP を経由しない (MAIL_MAILER=log 等) 場合や
 * CLI 経由の救済用途のために返却するが、 production でも email にも同じ URL が
 * 入る (token は 64 文字の暗号学的ランダム + 24 時間有効)。
 *
 * 自分自身を reset したケースでは requiresRelogin=true で、 frontend が
 * 既存 session を破棄して /login (招待メール待ち) に誘導する。
 */
final class PasswordResetResultDto
{
    public function __construct(
        public readonly UserDto $user,
        public readonly string $inviteUrl,
        public readonly string $inviteExpiresAt,
        public readonly bool $requiresRelogin,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'user' => $this->user->toArray(),
            'inviteUrl' => $this->inviteUrl,
            'inviteExpiresAt' => $this->inviteExpiresAt,
            'requiresRelogin' => $this->requiresRelogin,
        ];
    }
}
