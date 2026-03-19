<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Skill\SkillId;

final class SkillGapEntry
{
    private SkillId $skillId;
    private int $requiredHeadcount;
    private int $qualifiedHeadcount;

    public function __construct(SkillId $skillId, int $requiredHeadcount, int $qualifiedHeadcount)
    {
        $this->skillId = $skillId;
        $this->requiredHeadcount = $requiredHeadcount;
        $this->qualifiedHeadcount = $qualifiedHeadcount;
    }

    public function skillId(): SkillId
    {
        return $this->skillId;
    }

    public function requiredHeadcount(): int
    {
        return $this->requiredHeadcount;
    }

    public function qualifiedHeadcount(): int
    {
        return $this->qualifiedHeadcount;
    }

    /** 正=余剰、負=不足 */
    public function gap(): int
    {
        return $this->qualifiedHeadcount - $this->requiredHeadcount;
    }
}
