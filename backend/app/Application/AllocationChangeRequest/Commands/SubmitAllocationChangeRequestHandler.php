<?php

declare(strict_types=1);

namespace App\Application\AllocationChangeRequest\Commands;

use App\Application\AllocationChangeRequest\DTOs\AllocationChangeRequestDto;
use App\Domain\AllocationChangeRequest\AllocationChangeRequest;
use App\Domain\AllocationChangeRequest\AllocationChangeRequestRepositoryInterface;
use App\Domain\AllocationChangeRequest\ChangeRequestPayload;
use App\Domain\AllocationChangeRequest\ChangeRequestType;
use App\Infrastructure\Events\DomainEventDispatcher;
use DateTimeImmutable;

final class SubmitAllocationChangeRequestHandler
{
    public function __construct(
        private AllocationChangeRequestRepositoryInterface $repository,
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    public function handle(SubmitAllocationChangeRequestCommand $command): AllocationChangeRequestDto
    {
        $type = ChangeRequestType::fromString($command->type);
        $payload = match ($type) {
            ChangeRequestType::CreateAllocation => ChangeRequestPayload::forCreateAllocation($command->payload),
            ChangeRequestType::RevokeAllocation => ChangeRequestPayload::forRevokeAllocation($command->payload),
        };

        $request = new AllocationChangeRequest(
            id: $this->repository->nextIdentity(),
            type: $type,
            payload: $payload,
            requestedBy: $command->requestedBy,
            reason: $command->reason,
            requestedAt: new DateTimeImmutable,
        );

        $this->repository->save($request);
        $this->eventDispatcher->dispatchAll($request->pullDomainEvents());

        return AllocationChangeRequestDto::fromDomain($request);
    }
}
