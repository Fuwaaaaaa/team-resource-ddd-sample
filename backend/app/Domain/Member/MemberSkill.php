<?php

declare(strict_types=1);

namespace App\Domain\Member;

use App\Domain\Skill\SkillId;

final class MemberSkill
{
    private MemberSkillId $id;
    private SkillId $skillId;
    private SkillProficiency $proficiency;

    public function __construct(MemberSkillId $id, SkillId $skillId, SkillProficiency $proficiency)
    {
        $this->id = $id;
        $this->skillId = $skillId;
        $this->proficiency = $proficiency;
    }

    public function id(): MemberSkillId
    {
        return $this->id;
    }

    public function skillId(): SkillId
    {
        return $this->skillId;
    }

    public function proficiency(): SkillProficiency
    {
        return $this->proficiency;
    }

    public function updateProficiency(SkillProficiency $newLevel): void
    {
        $this->proficiency = $newLevel;
    }
}
