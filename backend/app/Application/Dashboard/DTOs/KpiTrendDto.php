<?php

declare(strict_types=1);

namespace App\Application\Dashboard\DTOs;

final class KpiTrendDto
{
    /** @param KpiTrendPointDto[] $points */
    public function __construct(
        public readonly string $referenceDate,
        public readonly int $days,
        public readonly array $points,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'referenceDate' => $this->referenceDate,
            'days' => $this->days,
            'points' => array_map(fn (KpiTrendPointDto $p): array => $p->toArray(), $this->points),
        ];
    }
}
