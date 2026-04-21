<?php

declare(strict_types=1);

namespace App\Application\Availability\Commands;

use App\Application\Availability\DTOs\AbsenceDto;
use App\Domain\Availability\Absence;
use App\Domain\Availability\AbsencePeriod;
use App\Domain\Availability\AbsenceRepositoryInterface;
use App\Domain\Availability\AbsenceType;
use App\Domain\Member\MemberId;
use App\Domain\Member\MemberRepositoryInterface;
use App\Infrastructure\Events\DomainEventDispatcher;
use DateTimeImmutable;
use InvalidArgumentException;

final class RegisterAbsenceHandler
{
    public function __construct(
        private AbsenceRepositoryInterface $absenceRepository,
        private MemberRepositoryInterface $memberRepository,
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    public function handle(RegisterAbsenceCommand $command): AbsenceDto
    {
        $memberId = new MemberId($command->memberId);
        if ($this->memberRepository->findById($memberId) === null) {
            throw new InvalidArgumentException("Member not found: {$command->memberId}");
        }

        $period = new AbsencePeriod(
            new DateTimeImmutable($command->startDate),
            new DateTimeImmutable($command->endDate),
        );

        $type = AbsenceType::tryFrom($command->type)
            ?? throw new InvalidArgumentException("Invalid absence type: {$command->type}");

        $absence = new Absence(
            id: $this->absenceRepository->nextIdentity(),
            memberId: $memberId,
            period: $period,
            type: $type,
            note: $command->note,
        );

        $this->absenceRepository->save($absence);
        $this->eventDispatcher->dispatchAll($absence->pullDomainEvents());

        return AbsenceDto::fromDomain($absence);
    }
}
