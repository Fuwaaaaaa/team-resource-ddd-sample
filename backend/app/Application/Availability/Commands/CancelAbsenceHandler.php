<?php

declare(strict_types=1);

namespace App\Application\Availability\Commands;

use App\Application\Availability\DTOs\AbsenceDto;
use App\Domain\Availability\AbsenceId;
use App\Domain\Availability\AbsenceRepositoryInterface;
use App\Infrastructure\Events\DomainEventDispatcher;
use InvalidArgumentException;

final class CancelAbsenceHandler
{
    public function __construct(
        private AbsenceRepositoryInterface $absenceRepository,
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    public function handle(string $absenceId): AbsenceDto
    {
        $absence = $this->absenceRepository->findById(new AbsenceId($absenceId));
        if ($absence === null) {
            throw new InvalidArgumentException("Absence not found: {$absenceId}");
        }

        $absence->cancel();
        $this->absenceRepository->save($absence);
        $this->eventDispatcher->dispatchAll($absence->pullDomainEvents());

        return AbsenceDto::fromDomain($absence);
    }
}
