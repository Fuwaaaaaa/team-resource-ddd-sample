<?php

declare(strict_types=1);

namespace App\Domain\Project\Events;

use App\Domain\Project\ProjectId;
use App\Domain\Skill\SkillId;

final class ProjectRequirementChanged
{
    private ProjectId $projectId;
    private SkillId $skillId;

    public function __construct(ProjectId $projectId, SkillId $skillId)
    {
        $this->projectId = $projectId;
        $this->skillId = $skillId;
    }

    public function projectId(): ProjectId
    {
        return $this->projectId;
    }

    public function skillId(): SkillId
    {
        return $this->skillId;
    }
}
