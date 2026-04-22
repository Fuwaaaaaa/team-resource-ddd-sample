<?php

declare(strict_types=1);

namespace App\Application\Dashboard\DTOs;

final class KpiSummaryDto
{
    public function __construct(
        public readonly string $referenceDate,
        public readonly float $averageFulfillmentRate,   // 0-100 (%)
        public readonly int $activeProjectCount,          // 集計対象プロジェクト数 (countsForCapacity)
        public readonly int $overloadedMemberCount,
        public readonly int $upcomingEndsThisWeek,        // 7 日以内に終了する active allocation 数
        public readonly int $skillGapsTotal,              // 全 project の不足人数合計
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'referenceDate' => $this->referenceDate,
            'averageFulfillmentRate' => $this->averageFulfillmentRate,
            'activeProjectCount' => $this->activeProjectCount,
            'overloadedMemberCount' => $this->overloadedMemberCount,
            'upcomingEndsThisWeek' => $this->upcomingEndsThisWeek,
            'skillGapsTotal' => $this->skillGapsTotal,
        ];
    }
}
