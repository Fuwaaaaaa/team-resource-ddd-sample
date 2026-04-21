<?php

declare(strict_types=1);

namespace App\Domain\Project;

use App\Domain\Skill\SkillId;
use InvalidArgumentException;

final class RequiredSkill
{
    private RequiredSkillId $id;

    private SkillId $skillId;

    private RequiredProficiency $minimumProficiency;

    private int $headcount;

    public function __construct(
        RequiredSkillId $id,
        SkillId $skillId,
        RequiredProficiency $minimumProficiency,
        int $headcount
    ) {
        if ($headcount < 1) {
            throw new InvalidArgumentException('Headcount must be at least 1.');
        }
        $this->id = $id;
        $this->skillId = $skillId;
        $this->minimumProficiency = $minimumProficiency;
        $this->headcount = $headcount;
    }

    public function id(): RequiredSkillId
    {
        return $this->id;
    }

    public function skillId(): SkillId
    {
        return $this->skillId;
    }

    public function minimumProficiency(): RequiredProficiency
    {
        return $this->minimumProficiency;
    }

    public function headcount(): int
    {
        return $this->headcount;
    }
}
