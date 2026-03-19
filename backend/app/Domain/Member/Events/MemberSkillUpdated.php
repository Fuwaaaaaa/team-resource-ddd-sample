<?php

declare(strict_types=1);

namespace App\Domain\Member\Events;

use App\Domain\Member\MemberId;
use App\Domain\Member\SkillProficiency;
use App\Domain\Skill\SkillId;

final class MemberSkillUpdated
{
    private MemberId $memberId;
    private SkillId $skillId;
    private SkillProficiency $proficiency;

    public function __construct(MemberId $memberId, SkillId $skillId, SkillProficiency $proficiency)
    {
        $this->memberId = $memberId;
        $this->skillId = $skillId;
        $this->proficiency = $proficiency;
    }

    public function memberId(): MemberId
    {
        return $this->memberId;
    }

    public function skillId(): SkillId
    {
        return $this->skillId;
    }

    public function proficiency(): SkillProficiency
    {
        return $this->proficiency;
    }
}
