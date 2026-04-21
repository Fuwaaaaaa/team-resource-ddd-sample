<?php

declare(strict_types=1);

namespace App\Application\Availability\DTOs;

use App\Domain\Availability\Absence;

final class AbsenceDto
{
    public function __construct(
        public string $id,
        public string $memberId,
        public string $startDate, // Y-m-d
        public string $endDate,   // Y-m-d
        public string $type,
        public string $note,
        public bool $canceled,
        public int $daysInclusive,
    ) {}

    public static function fromDomain(Absence $absence): self
    {
        return new self(
            id: $absence->id()->toString(),
            memberId: $absence->memberId()->toString(),
            startDate: $absence->period()->startDate()->format('Y-m-d'),
            endDate: $absence->period()->endDate()->format('Y-m-d'),
            type: $absence->type()->value,
            note: $absence->note(),
            canceled: $absence->isCanceled(),
            daysInclusive: $absence->period()->daysInclusive(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'memberId' => $this->memberId,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'type' => $this->type,
            'note' => $this->note,
            'canceled' => $this->canceled,
            'daysInclusive' => $this->daysInclusive,
        ];
    }
}
