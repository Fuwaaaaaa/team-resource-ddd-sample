<?php

declare(strict_types=1);

namespace App\Application\Allocation\Commands;

use App\Application\Allocation\DTOs\AllocationDto;
use App\Application\Allocation\Exceptions\AllocationCapacityExceededException;
use App\Domain\Allocation\AllocationPercentage;
use App\Domain\Allocation\AllocationPeriod;
use App\Domain\Allocation\ResourceAllocation;
use App\Domain\Allocation\ResourceAllocationRepositoryInterface;
use App\Domain\Member\MemberId;
use App\Domain\Project\ProjectId;
use App\Domain\Service\AllocationServiceInterface;
use App\Domain\Skill\SkillId;
use App\Infrastructure\Events\DomainEventDispatcher;
use DateTimeImmutable;

final class CreateAllocationHandler
{
    public function __construct(
        private ResourceAllocationRepositoryInterface $allocationRepository,
        private AllocationServiceInterface $allocationService,
        private DomainEventDispatcher $eventDispatcher,
    ) {
    }

    public function handle(CreateAllocationCommand $command): AllocationDto
    {
        $memberId = new MemberId($command->memberId);
        $requested = new AllocationPercentage($command->allocationPercentage);
        $periodStart = new DateTimeImmutable($command->periodStart);
        $periodEnd = new DateTimeImmutable($command->periodEnd);

        $existing = $this->allocationRepository->findByMemberId($memberId);

        if (! $this->allocationService->canAllocate($memberId, $requested, $existing, $periodStart)) {
            throw new AllocationCapacityExceededException(
                $command->memberId,
                $command->allocationPercentage,
            );
        }

        $allocation = new ResourceAllocation(
            $this->allocationRepository->nextIdentity(),
            $memberId,
            new ProjectId($command->projectId),
            new SkillId($command->skillId),
            $requested,
            new AllocationPeriod($periodStart, $periodEnd),
        );

        $this->allocationRepository->save($allocation);

        $this->eventDispatcher->dispatchAll($allocation->pullDomainEvents());

        return AllocationDto::fromDomain($allocation);
    }
}
