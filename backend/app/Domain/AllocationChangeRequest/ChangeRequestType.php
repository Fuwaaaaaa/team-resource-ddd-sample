<?php

declare(strict_types=1);

namespace App\Domain\AllocationChangeRequest;

use InvalidArgumentException;

enum ChangeRequestType: string
{
    case CreateAllocation = 'create_allocation';
    case RevokeAllocation = 'revoke_allocation';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new InvalidArgumentException('Invalid ChangeRequestType: '.$value);
    }
}
