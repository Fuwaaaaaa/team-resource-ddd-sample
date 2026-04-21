<?php

declare(strict_types=1);

namespace App\Domain\Availability;

use App\Domain\Member\MemberId;

interface AbsenceRepositoryInterface
{
    public function findById(AbsenceId $id): ?Absence;

    /** @return Absence[] */
    public function findAll(): array;

    /** @return Absence[] */
    public function findByMemberId(MemberId $memberId): array;

    /** @return Absence[] アクティブな不在のみ */
    public function findActive(): array;

    public function save(Absence $absence): void;

    public function nextIdentity(): AbsenceId;
}
