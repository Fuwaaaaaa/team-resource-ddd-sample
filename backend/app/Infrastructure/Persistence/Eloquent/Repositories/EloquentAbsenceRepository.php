<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Availability\Absence;
use App\Domain\Availability\AbsenceId;
use App\Domain\Availability\AbsenceRepositoryInterface;
use App\Domain\Member\MemberId;
use App\Infrastructure\Persistence\Eloquent\Mappers\AbsenceMapper;
use App\Infrastructure\Persistence\Eloquent\Models\AbsenceModel;
use Illuminate\Support\Str;

final class EloquentAbsenceRepository implements AbsenceRepositoryInterface
{
    public function findById(AbsenceId $id): ?Absence
    {
        $model = AbsenceModel::find($id->toString());

        return $model ? AbsenceMapper::toDomain($model) : null;
    }

    /** @return Absence[] */
    public function findAll(): array
    {
        return AbsenceModel::orderBy('start_date', 'desc')->get()
            ->map(fn (AbsenceModel $m) => AbsenceMapper::toDomain($m))
            ->all();
    }

    /** @return Absence[] */
    public function findByMemberId(MemberId $memberId): array
    {
        return AbsenceModel::where('member_id', $memberId->toString())
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(fn (AbsenceModel $m) => AbsenceMapper::toDomain($m))
            ->all();
    }

    /** @return Absence[] */
    public function findActive(): array
    {
        return AbsenceModel::where('canceled', false)
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(fn (AbsenceModel $m) => AbsenceMapper::toDomain($m))
            ->all();
    }

    public function save(Absence $absence): void
    {
        AbsenceModel::updateOrCreate(
            ['id' => $absence->id()->toString()],
            AbsenceMapper::toRow($absence),
        );
    }

    public function nextIdentity(): AbsenceId
    {
        return new AbsenceId((string) Str::uuid7());
    }
}
