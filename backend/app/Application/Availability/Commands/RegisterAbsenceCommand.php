<?php

declare(strict_types=1);

namespace App\Application\Availability\Commands;

final class RegisterAbsenceCommand
{
    public function __construct(
        public string $memberId,
        public string $startDate, // Y-m-d
        public string $endDate,   // Y-m-d
        public string $type,      // AbsenceType value
        public string $note = '',
    ) {}
}
