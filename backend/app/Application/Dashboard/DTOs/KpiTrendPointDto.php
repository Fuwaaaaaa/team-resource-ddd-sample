<?php

declare(strict_types=1);

namespace App\Application\Dashboard\DTOs;

final class KpiTrendPointDto
{
    public function __construct(
        public readonly string $date, // Y-m-d
        public readonly float $averageFulfillmentRate,
        public readonly int $activeProjectCount,
        public readonly int $overloadedMemberCount,
        public readonly int $upcomingEndsThisWeek,
        public readonly int $skillGapsTotal,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'averageFulfillmentRate' => $this->averageFulfillmentRate,
            'activeProjectCount' => $this->activeProjectCount,
            'overloadedMemberCount' => $this->overloadedMemberCount,
            'upcomingEndsThisWeek' => $this->upcomingEndsThisWeek,
            'skillGapsTotal' => $this->skillGapsTotal,
        ];
    }
}
