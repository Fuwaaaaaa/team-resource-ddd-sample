<?php

declare(strict_types=1);

namespace App\Application\Availability\Queries;

use App\Application\Availability\DTOs\AbsenceDto;
use App\Domain\Availability\AbsenceRepositoryInterface;
use App\Domain\Member\MemberId;

final class ListAbsencesByMemberHandler
{
    public function __construct(
        private AbsenceRepositoryInterface $absenceRepository,
    ) {
    }

    /** @return AbsenceDto[] */
    public function handle(string $memberId): array
    {
        $absences = $this->absenceRepository->findByMemberId(new MemberId($memberId));
        return array_map(fn ($a) => AbsenceDto::fromDomain($a), $absences);
    }
}
