<?php

declare(strict_types=1);

namespace App\Domain\Allocation;

use DateTimeImmutable;
use InvalidArgumentException;

final class AllocationPeriod
{
    private DateTimeImmutable $startDate;

    private DateTimeImmutable $endDate;

    public function __construct(DateTimeImmutable $startDate, DateTimeImmutable $endDate)
    {
        if ($endDate <= $startDate) {
            throw new InvalidArgumentException('End date must be after start date.');
        }
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function startDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    public function endDate(): DateTimeImmutable
    {
        return $this->endDate;
    }

    public function contains(DateTimeImmutable $date): bool
    {
        return $date >= $this->startDate && $date <= $this->endDate;
    }

    public function overlaps(self $other): bool
    {
        return $this->startDate <= $other->endDate && $this->endDate >= $other->startDate;
    }

    public function equals(self $other): bool
    {
        return $this->startDate == $other->startDate && $this->endDate == $other->endDate;
    }
}
