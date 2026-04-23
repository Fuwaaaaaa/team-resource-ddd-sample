<?php

declare(strict_types=1);

namespace App\Application\AllocationChangeRequest\Commands;

use App\Application\AllocationChangeRequest\DTOs\AllocationChangeRequestDto;
use App\Domain\AllocationChangeRequest\AllocationChangeRequestId;
use App\Domain\AllocationChangeRequest\AllocationChangeRequestRepositoryInterface;
use App\Infrastructure\Events\DomainEventDispatcher;
use DateTimeImmutable;
use RuntimeException;

final class RejectAllocationChangeRequestHandler
{
    public function __construct(
        private AllocationChangeRequestRepositoryInterface $repository,
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    public function handle(string $requestId, int $decidedBy, ?string $decisionNote = null): AllocationChangeRequestDto
    {
        $request = $this->repository->findById(new AllocationChangeRequestId($requestId));
        if ($request === null) {
            throw new RuntimeException('AllocationChangeRequest not found: '.$requestId);
        }

        $request->reject(
            decidedBy: $decidedBy,
            decidedAt: new DateTimeImmutable,
            note: $decisionNote,
        );

        $this->repository->save($request);
        $this->eventDispatcher->dispatchAll($request->pullDomainEvents());

        return AllocationChangeRequestDto::fromDomain($request);
    }
}
