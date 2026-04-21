<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Member\MemberId;
use App\Domain\Project\ProjectId;
use App\Domain\Skill\SkillId;

final class SkillGapWarning
{
    private MemberId $memberId;

    private ProjectId $projectId;

    private SkillId $skillId;

    private int $requiredLevel;

    private ?int $actualLevel;

    public function __construct(
        MemberId $memberId,
        ProjectId $projectId,
        SkillId $skillId,
        int $requiredLevel,
        ?int $actualLevel
    ) {
        $this->memberId = $memberId;
        $this->projectId = $projectId;
        $this->skillId = $skillId;
        $this->requiredLevel = $requiredLevel;
        $this->actualLevel = $actualLevel;
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

    public function requiredLevel(): int
    {
        return $this->requiredLevel;
    }

    public function actualLevel(): ?int
    {
        return $this->actualLevel;
    }

    /** 不足度（常に正の値、大きいほど深刻） */
    public function deficitLevel(): int
    {
        return $this->requiredLevel - ($this->actualLevel ?? 0);
    }
}
