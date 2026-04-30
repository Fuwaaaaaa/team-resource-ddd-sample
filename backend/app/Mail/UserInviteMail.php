<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 新規ユーザーに送信する招待メール。
 *
 * Body にリンクを 1 本だけ載せる: {APP_URL}/invite/{token}
 * 24 時間有効、 single-use。
 */
final class UserInviteMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $userName,
        public readonly string $role,
        public readonly string $inviteUrl,
        public readonly string $expiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Team Resource Dashboard へようこそ',
        );
    }

    public function build(): self
    {
        $name = htmlspecialchars($this->userName, ENT_QUOTES, 'UTF-8');
        $role = htmlspecialchars($this->role, ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars($this->inviteUrl, ENT_QUOTES, 'UTF-8');
        $expiresAt = htmlspecialchars($this->expiresAt, ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="ja">
<body style="font-family: sans-serif; padding: 16px;">
  <div style="max-width: 560px; margin: 0 auto;">
    <h1 style="font-size: 18px; margin: 0 0 16px;">ようこそ、{$name} さん</h1>
    <p style="font-size: 14px; color: #333; line-height: 1.6;">
      Team Resource Dashboard に <strong>{$role}</strong> ロールで招待されました。
      下記のリンクからパスワードを設定してログインしてください。
    </p>
    <p style="margin: 24px 0;">
      <a href="{$url}"
         style="display: inline-block; background: #2563eb; color: #fff; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-size: 14px;">
        パスワードを設定する
      </a>
    </p>
    <p style="font-size: 12px; color: #666;">
      このリンクは <strong>{$expiresAt}</strong> に失効します (24 時間)。<br>
      再発行は admin に依頼してください。
    </p>
    <p style="font-size: 11px; color: #999; word-break: break-all; margin-top: 16px;">
      リンクが開かない場合は次の URL を直接コピーしてください:<br>
      {$url}
    </p>
    <hr style="margin: 24px 0; border: none; border-top: 1px solid #eee;">
    <p style="font-size: 11px; color: #999;">Team Resource Dashboard &middot; 自動送信</p>
  </div>
</body>
</html>
HTML;

        return $this->html($html);
    }
}
