<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Allocation\AllocationId;
use App\Domain\Allocation\ResourceAllocation;
use App\Domain\Allocation\ResourceAllocationRepositoryInterface;
use App\Domain\Member\MemberId;
use App\Domain\Project\ProjectId;
use App\Infrastructure\Persistence\Eloquent\Mappers\AllocationMapper;
use App\Infrastructure\Persistence\Eloquent\Models\AllocationModel;
use DateTimeImmutable;
use Illuminate\Support\Str;

final class EloquentAllocationRepository implements ResourceAllocationRepositoryInterface
{
    public function findById(AllocationId $id): ?ResourceAllocation
    {
        $model = AllocationModel::find($id->toString());
        return $model ? AllocationMapper::toDomain($model) : null;
    }

    /** @return ResourceAllocation[] */
    public function findByProjectId(ProjectId $projectId): array
    {
        return AllocationModel::where('project_id', $projectId->toString())->get()
            ->map(fn (AllocationModel $m) => AllocationMapper::toDomain($m))
            ->all();
    }

    /** @return ResourceAllocation[] */
    public function findByMemberId(MemberId $memberId): array
    {
        return AllocationModel::where('member_id', $memberId->toString())->get()
            ->map(fn (AllocationModel $m) => AllocationMapper::toDomain($m))
            ->all();
    }

    /** @return ResourceAllocation[] */
    public function findActiveOnDate(DateTimeImmutable $date): array
    {
        $iso = $date->format('Y-m-d');
        return AllocationModel::where('status', 'active')
            ->whereDate('period_start', '<=', $iso)
            ->whereDate('period_end', '>=', $iso)
            ->get()
            ->map(fn (AllocationModel $m) => AllocationMapper::toDomain($m))
            ->all();
    }

    public function save(ResourceAllocation $allocation): void
    {
        AllocationModel::updateOrCreate(
            ['id' => $allocation->id()->toString()],
            AllocationMapper::toRow($allocation),
        );
    }

    public function nextIdentity(): AllocationId
    {
        return new AllocationId((string) Str::uuid7());
    }
}
