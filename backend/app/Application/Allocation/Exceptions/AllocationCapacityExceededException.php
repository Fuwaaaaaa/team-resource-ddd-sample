<?php

declare(strict_types=1);

namespace App\Application\Allocation\Exceptions;

use DomainException;

final class AllocationCapacityExceededException extends DomainException
{
    public function __construct(string $memberId, int $requested)
    {
        parent::__construct(sprintf(
            'Member %s cannot accept %d%% allocation: capacity would exceed 100%%.',
            $memberId,
            $requested,
        ));
    }
}
