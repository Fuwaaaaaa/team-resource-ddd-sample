<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\AllocationChangeRequest\AllocationChangeRequest;
use App\Domain\AllocationChangeRequest\AllocationChangeRequestId;
use App\Domain\AllocationChangeRequest\AllocationChangeRequestRepositoryInterface;
use App\Domain\AllocationChangeRequest\ChangeRequestStatus;
use App\Infrastructure\Persistence\Eloquent\Mappers\AllocationChangeRequestMapper;
use App\Infrastructure\Persistence\Eloquent\Models\AllocationChangeRequestModel;
use Illuminate\Support\Str;

final class EloquentAllocationChangeRequestRepository implements AllocationChangeRequestRepositoryInterface
{
    public function findById(AllocationChangeRequestId $id): ?AllocationChangeRequest
    {
        $model = AllocationChangeRequestModel::find($id->toString());

        return $model ? AllocationChangeRequestMapper::toDomain($model) : null;
    }

    /** @return AllocationChangeRequest[] */
    public function findList(?ChangeRequestStatus $status = null, ?int $requestedBy = null): array
    {
        $query = AllocationChangeRequestModel::query()->orderByDesc('requested_at');
        if ($status !== null) {
            $query->where('status', $status->value);
        }
        if ($requestedBy !== null) {
            $query->where('requested_by', $requestedBy);
        }

        return $query->get()
            ->map(fn (AllocationChangeRequestModel $m) => AllocationChangeRequestMapper::toDomain($m))
            ->all();
    }

    public function save(AllocationChangeRequest $request): void
    {
        AllocationChangeRequestModel::updateOrCreate(
            ['id' => $request->id()->toString()],
            AllocationChangeRequestMapper::toRow($request),
        );
    }

    public function nextIdentity(): AllocationChangeRequestId
    {
        return new AllocationChangeRequestId((string) Str::uuid7());
    }
}
