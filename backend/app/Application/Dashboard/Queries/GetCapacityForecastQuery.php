<?php

declare(strict_types=1);

namespace App\Application\Dashboard\Queries;

final class GetCapacityForecastQuery
{
    public function __construct(
        public string $referenceDate, // Y-m-d
        public int $monthsAhead,
    ) {}
}
