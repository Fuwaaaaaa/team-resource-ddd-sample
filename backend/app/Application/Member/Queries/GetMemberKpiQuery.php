<?php

declare(strict_types=1);

namespace App\Application\Member\Queries;

final class GetMemberKpiQuery
{
    public function __construct(
        public string $memberId,
        public string $referenceDate, // Y-m-d
    ) {}
}
