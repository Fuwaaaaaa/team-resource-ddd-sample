<?php

declare(strict_types=1);

namespace App\Mail;

use App\Notifications\NotificationContent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class NotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly NotificationContent $content) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '['.$this->content->severity->value.'] '.$this->content->title,
        );
    }

    public function build(): self
    {
        $severity = $this->content->severity->value;
        $type = $this->content->type;
        $title = htmlspecialchars($this->content->title, ENT_QUOTES, 'UTF-8');
        $body = nl2br(htmlspecialchars($this->content->body, ENT_QUOTES, 'UTF-8'));

        $html = <<<HTML
<!DOCTYPE html>
<html lang="ja">
<body style="font-family: sans-serif; padding: 16px;">
  <div style="max-width: 560px; margin: 0 auto;">
    <div style="font-size: 11px; color: #666; letter-spacing: 1px; text-transform: uppercase;">
      {$severity} — {$type}
    </div>
    <h1 style="font-size: 18px; margin: 8px 0;">{$title}</h1>
    <p style="font-size: 14px; color: #333; line-height: 1.6;">{$body}</p>
    <hr style="margin: 24px 0; border: none; border-top: 1px solid #eee;">
    <p style="font-size: 11px; color: #999;">Team Resource Dashboard &middot; 自動通知</p>
  </div>
</body>
</html>
HTML;

        return $this->html($html);
    }
}
