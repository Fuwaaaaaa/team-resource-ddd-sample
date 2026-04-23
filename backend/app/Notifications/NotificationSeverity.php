<?php

declare(strict_types=1);

namespace App\Notifications;

use InvalidArgumentException;

enum NotificationSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new InvalidArgumentException('Invalid NotificationSeverity: '.$value);
    }

    private function rank(): int
    {
        return match ($this) {
            self::Info => 0,
            self::Warning => 1,
            self::Critical => 2,
        };
    }

    public function atLeast(self $threshold): bool
    {
        return $this->rank() >= $threshold->rank();
    }
}
