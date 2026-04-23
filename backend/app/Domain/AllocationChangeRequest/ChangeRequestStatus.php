<?php

declare(strict_types=1);

namespace App\Domain\AllocationChangeRequest;

use InvalidArgumentException;

enum ChangeRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new InvalidArgumentException('Invalid ChangeRequestStatus: '.$value);
    }

    public function isDecided(): bool
    {
        return $this !== self::Pending;
    }
}
