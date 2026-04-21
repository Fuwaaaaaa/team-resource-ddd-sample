<?php

declare(strict_types=1);

namespace App\Application\Dashboard\Queries;

final class GetTeamCapacityQuery
{
    public function __construct(
        public readonly string $referenceDate,
    ) {
    }
}
