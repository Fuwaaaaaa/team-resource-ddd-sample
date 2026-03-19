<?php

declare(strict_types=1);

namespace App\Domain\Allocation;

use App\Domain\Member\MemberId;
use App\Domain\Project\ProjectId;
use App\Domain\Skill\SkillId;
use DateTimeImmutable;

final class ResourceAllocation
{
    private AllocationId $id;
    private MemberId $memberId;
    private ProjectId $projectId;
    private SkillId $skillId;
    private AllocationPercentage $percentage;
    private AllocationPeriod $period;
    private AllocationStatus $status;
    /** @var object[] */
    private array $domainEvents = [];

    public function __construct(
        AllocationId $id,
        MemberId $memberId,
        ProjectId $projectId,
        SkillId $skillId,
        AllocationPercentage $percentage,
        AllocationPeriod $period
    ) {
        $this->id = $id;
        $this->memberId = $memberId;
        $this->projectId = $projectId;
        $this->skillId = $skillId;
        $this->percentage = $percentage;
        $this->period = $period;
        $this->status = AllocationStatus::active();

        $this->domainEvents[] = new Events\AllocationCreated(
            $id,
            $memberId,
            $projectId,
            $skillId,
            $percentage
        );
    }

    public function id(): AllocationId
    {
        return $this->id;
    }

    public function memberId(): MemberId
    {
        return $this->memberId;
    }

    public function projectId(): ProjectId
    {
        return $this->projectId;
    }

    public function skillId(): SkillId
    {
        return $this->skillId;
    }

    public function percentage(): AllocationPercentage
    {
        return $this->percentage;
    }

    public function period(): AllocationPeriod
    {
        return $this->period;
    }

    public function status(): AllocationStatus
    {
        return $this->status;
    }

    public function revoke(): void
    {
        $this->status = AllocationStatus::revoked();
        $this->domainEvents[] = new Events\AllocationRevoked($this->id);
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function coversDate(DateTimeImmutable $date): bool
    {
        return $this->period->contains($date);
    }

    /** @return object[] */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }
}
