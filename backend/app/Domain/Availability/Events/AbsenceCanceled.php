<?php

declare(strict_types=1);

namespace App\Domain\Availability\Events;

use App\Domain\Availability\AbsenceId;
use App\Domain\Member\MemberId;

final class AbsenceCanceled
{
    public function __construct(
        private AbsenceId $absenceId,
        private MemberId $memberId,
    ) {
    }

    public function absenceId(): AbsenceId
    {
        return $this->absenceId;
    }

    public function memberId(): MemberId
    {
        return $this->memberId;
    }
}
