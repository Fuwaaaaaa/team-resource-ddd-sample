<?php

declare(strict_types=1);

namespace App\Application\Dashboard\DTOs;

final class CapacityForecastDto
{
    /** @param ForecastBucketDto[] $buckets */
    public function __construct(
        public readonly string $referenceDate,
        public readonly int $monthsAhead,
        public readonly array $buckets,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'referenceDate' => $this->referenceDate,
            'monthsAhead' => $this->monthsAhead,
            'buckets' => array_map(fn (ForecastBucketDto $b): array => $b->toArray(), $this->buckets),
        ];
    }
}
