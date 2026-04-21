<?php

declare(strict_types=1);

namespace App\Domain\Member;

use InvalidArgumentException;

final class StandardWorkingHours
{
    private float $hoursPerDay;

    public function __construct(float $hoursPerDay)
    {
        if ($hoursPerDay <= 0.0 || $hoursPerDay > 24.0) {
            throw new InvalidArgumentException(
                "Standard working hours must be between 0 (exclusive) and 24, got {$hoursPerDay}."
            );
        }
        $this->hoursPerDay = $hoursPerDay;
    }

    public function hoursPerDay(): float
    {
        return $this->hoursPerDay;
    }

    /** 割当%を実稼働時間に変換 */
    public function toHours(int $totalPercentage): float
    {
        return $this->hoursPerDay * ($totalPercentage / 100.0);
    }

    /** 100%超過判定 */
    public function isOverloaded(int $totalAllocatedPercentage): bool
    {
        return $totalAllocatedPercentage > 100;
    }

    /** 超過分の時間（0以上） */
    public function overloadHours(int $totalAllocatedPercentage): float
    {
        $excess = max(0, $totalAllocatedPercentage - 100);

        return $this->hoursPerDay * ($excess / 100.0);
    }

    public function equals(self $other): bool
    {
        return $this->hoursPerDay === $other->hoursPerDay;
    }
}
