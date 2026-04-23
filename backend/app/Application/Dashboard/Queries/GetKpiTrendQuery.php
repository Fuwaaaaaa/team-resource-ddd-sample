<?php

declare(strict_types=1);

namespace App\Application\Dashboard\Queries;

final class GetKpiTrendQuery
{
    public function __construct(
        public string $referenceDate, // Y-m-d
        public int $days,             // 7 | 30 | 90
    ) {}
}
