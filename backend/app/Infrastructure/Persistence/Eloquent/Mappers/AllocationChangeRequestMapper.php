<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Mappers;

use App\Domain\AllocationChangeRequest\AllocationChangeRequest;
use App\Domain\AllocationChangeRequest\AllocationChangeRequestId;
use App\Domain\AllocationChangeRequest\ChangeRequestPayload;
use App\Domain\AllocationChangeRequest\ChangeRequestStatus;
use App\Domain\AllocationChangeRequest\ChangeRequestType;
use App\Infrastructure\Persistence\Eloquent\Models\AllocationChangeRequestModel;
use DateTimeImmutable;
use ReflectionClass;

final class AllocationChangeRequestMapper
{
    public static function toDomain(AllocationChangeRequestModel $model): AllocationChangeRequest
    {
        $ref = new ReflectionClass(AllocationChangeRequest::class);
        /** @var AllocationChangeRequest $request */
        $request = $ref->newInstanceWithoutConstructor();

        $requestedAt = $model->requested_at instanceof \DateTimeInterface
            ? DateTimeImmutable::createFromInterface($model->requested_at)
            : new DateTimeImmutable((string) $model->requested_at);
        $decidedAt = null;
        if ($model->decided_at !== null) {
            $decidedAt = $model->decided_at instanceof \DateTimeInterface
                ? DateTimeImmutable::createFromInterface($model->decided_at)
                : new DateTimeImmutable((string) $model->decided_at);
        }

        $props = [
            'id' => new AllocationChangeRequestId((string) $model->id),
            'type' => ChangeRequestType::fromString((string) $model->type),
            'payload' => new ChangeRequestPayload((array) ($model->payload ?? [])),
            'requestedBy' => (int) $model->requested_by,
            'reason' => $model->reason,
            'status' => ChangeRequestStatus::fromString((string) $model->status),
            'requestedAt' => $requestedAt,
            'decidedBy' => $model->decided_by !== null ? (int) $model->decided_by : null,
            'decidedAt' => $decidedAt,
            'decisionNote' => $model->decision_note,
            'resultingAllocationId' => $model->resulting_allocation_id,
            'domainEvents' => [],
        ];

        foreach ($props as $name => $value) {
            $prop = $ref->getProperty($name);
            $prop->setValue($request, $value);
        }

        return $request;
    }

    /** @return array<string, mixed> */
    public static function toRow(AllocationChangeRequest $request): array
    {
        return [
            'type' => $request->type()->value,
            'payload' => $request->payload()->toArray(),
            'requested_by' => $request->requestedBy(),
            'reason' => $request->reason(),
            'status' => $request->status()->value,
            'requested_at' => $request->requestedAt()->format('Y-m-d H:i:s'),
            'decided_by' => $request->decidedBy(),
            'decided_at' => $request->decidedAt()?->format('Y-m-d H:i:s'),
            'decision_note' => $request->decisionNote(),
            'resulting_allocation_id' => $request->resultingAllocationId(),
        ];
    }
}
