<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Member\MemberId;

final class MemberOverloadEntry
{
    private MemberId $memberId;

    private float $standardHoursPerDay;

    private int $totalAllocatedPercentage;

    private float $overloadHours;

    public function __construct(
        MemberId $memberId,
        float $standardHoursPerDay,
        int $totalAllocatedPercentage,
        float $overloadHours
    ) {
        $this->memberId = $memberId;
        $this->standardHoursPerDay = $standardHoursPerDay;
        $this->totalAllocatedPercentage = $totalAllocatedPercentage;
        $this->overloadHours = $overloadHours;
    }

    public function memberId(): MemberId
    {
        return $this->memberId;
    }

    public function standardHoursPerDay(): float
    {
        return $this->standardHoursPerDay;
    }

    public function totalAllocatedPercentage(): int
    {
        return $this->totalAllocatedPercentage;
    }

    public function overloadHours(): float
    {
        return $this->overloadHours;
    }

    public function isOverloaded(): bool
    {
        return $this->totalAllocatedPercentage > 100;
    }
}
