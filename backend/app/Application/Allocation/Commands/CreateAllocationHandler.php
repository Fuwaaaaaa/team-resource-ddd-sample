<?php

declare(strict_types=1);

namespace App\Application\Allocation\Commands;

use App\Application\Allocation\DTOs\AllocationDto;
use App\Application\Allocation\DTOs\AllocationSimulationDto;
use App\Application\Allocation\Exceptions\AllocationCapacityExceededException;
use App\Domain\Allocation\AllocationPercentage;
use App\Domain\Allocation\AllocationPeriod;
use App\Domain\Allocation\ResourceAllocation;
use App\Domain\Allocation\ResourceAllocationRepositoryInterface;
use App\Domain\Member\MemberId;
use App\Domain\Member\MemberRepositoryInterface;
use App\Domain\Project\ProjectId;
use App\Domain\Service\AllocationServiceInterface;
use App\Domain\Skill\SkillId;
use App\Infrastructure\Events\DomainEventDispatcher;
use DateTimeImmutable;

final class CreateAllocationHandler
{
    public function __construct(
        private ResourceAllocationRepositoryInterface $allocationRepository,
        private MemberRepositoryInterface $memberRepository,
        private AllocationServiceInterface $allocationService,
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    /**
     * @return AllocationDto|AllocationSimulationDto dryRun=true のときはシミュレーション結果、
     *                                               そうでなければ実作成された Allocation の DTO
     */
    public function handle(CreateAllocationCommand $command): AllocationDto|AllocationSimulationDto
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

        if ($command->dryRun) {
            // 書込・イベント発火せず、作成されるとどうなるかを試算して返す
            $allocation->pullDomainEvents(); // 破棄 (未発火)

            $currentTotal = 0;
            foreach ($existing as $e) {
                if ($e->isActive() && $e->coversDate($periodStart)) {
                    $currentTotal += $e->percentage()->value();
                }
            }
            $projectedTotal = $currentTotal + $command->allocationPercentage;
            $member = $this->memberRepository->findById($memberId);
            $standardHours = $member?->standardWorkingHours();
            $overloadHours = $standardHours?->overloadHours($projectedTotal) ?? 0.0;

            return new AllocationSimulationDto(
                wouldCreate: AllocationDto::fromDomain($allocation),
                currentTotalPercentage: $currentTotal,
                projectedTotalPercentage: $projectedTotal,
                projectedAvailablePercentage: max(0, 100 - $projectedTotal),
                projectedOverloaded: $projectedTotal > 100,
                projectedOverloadHours: $overloadHours,
            );
        }

        $this->allocationRepository->save($allocation);

        $this->eventDispatcher->dispatchAll($allocation->pullDomainEvents());

        return AllocationDto::fromDomain($allocation);
    }
}
