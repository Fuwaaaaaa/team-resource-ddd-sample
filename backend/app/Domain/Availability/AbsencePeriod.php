<?php

declare(strict_types=1);

namespace App\Domain\Availability;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * 不在期間（開始日・終了日を含む閉区間）。
 *
 * AllocationPeriod と異なり、単日不在（start = end）を許容する。
 */
final class AbsencePeriod
{
    private DateTimeImmutable $startDate;
    private DateTimeImmutable $endDate;

    public function __construct(DateTimeImmutable $startDate, DateTimeImmutable $endDate)
    {
        if ($endDate < $startDate) {
            throw new InvalidArgumentException('End date must be on or after start date.');
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

    /** 営業日を無視した暦日数。単日不在なら 1。 */
    public function daysInclusive(): int
    {
        $diff = $this->startDate->diff($this->endDate);
        return ((int) $diff->days) + 1;
    }
}
