<?php

declare(strict_types=1);

namespace App\Notifications;

final class NotificationContent
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly string $body,
        public readonly array $payload,
        public readonly NotificationSeverity $severity,
    ) {}
}
