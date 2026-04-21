<?php

declare(strict_types=1);

namespace App\Domain\Allocation\Events;

use App\Domain\Allocation\AllocationId;
use App\Domain\Allocation\AllocationPercentage;
use App\Domain\Member\MemberId;
use App\Domain\Project\ProjectId;
use App\Domain\Skill\SkillId;

final class AllocationCreated
{
    private AllocationId $allocationId;

    private MemberId $memberId;

    private ProjectId $projectId;

    private SkillId $skillId;

    private AllocationPercentage $percentage;

    public function __construct(
        AllocationId $allocationId,
        MemberId $memberId,
        ProjectId $projectId,
        SkillId $skillId,
        AllocationPercentage $percentage
    ) {
        $this->allocationId = $allocationId;
        $this->memberId = $memberId;
        $this->projectId = $projectId;
        $this->skillId = $skillId;
        $this->percentage = $percentage;
    }

    public function allocationId(): AllocationId
    {
        return $this->allocationId;
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
}
