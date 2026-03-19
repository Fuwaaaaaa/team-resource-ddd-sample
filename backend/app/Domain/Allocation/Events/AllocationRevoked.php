<?php

declare(strict_types=1);

namespace App\Domain\Allocation\Events;

use App\Domain\Allocation\AllocationId;

final class AllocationRevoked
{
    private AllocationId $allocationId;

    public function __construct(AllocationId $allocationId)
    {
        $this->allocationId = $allocationId;
    }

    public function allocationId(): AllocationId
    {
        return $this->allocationId;
    }
}
