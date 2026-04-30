<?php

declare(strict_types=1);

namespace App\Application\Admin\DTOs;

/**
 * 招待リンクフロー (TODO-3) 移行後の admin user create のレスポンス。
 *
 * Password は admin に渡らない。 代わりに招待 email が送信され、 受信者本人が
 * password を設定する。 inviteUrl は dev / staging で SMTP を経由しない場合
 * (MAIL_MAILER=log 等) や CLI 用に同梱する。 production では admin が
 * URL を見ても email にも同じ URL が載っている (悪用防止のための token の値は
 * 64 文字の暗号学的ランダム + 24 時間有効)。
 */
final class CreatedUserDto
{
    public function __construct(
        public readonly UserDto $user,
        public readonly string $inviteSentTo,
        public readonly string $inviteExpiresAt,
        public readonly string $inviteUrl,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'user' => $this->user->toArray(),
            'inviteSentTo' => $this->inviteSentTo,
            'inviteExpiresAt' => $this->inviteExpiresAt,
            'inviteUrl' => $this->inviteUrl,
        ];
    }
}
