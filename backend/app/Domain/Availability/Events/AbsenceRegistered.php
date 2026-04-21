<?php

declare(strict_types=1);

namespace App\Domain\Availability\Events;

use App\Domain\Availability\AbsenceId;
use App\Domain\Availability\AbsencePeriod;
use App\Domain\Availability\AbsenceType;
use App\Domain\Member\MemberId;

final class AbsenceRegistered
{
    public function __construct(
        private AbsenceId $absenceId,
        private MemberId $memberId,
        private AbsencePeriod $period,
        private AbsenceType $type,
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

    public function period(): AbsencePeriod
    {
        return $this->period;
    }

    public function type(): AbsenceType
    {
        return $this->type;
    }
}
