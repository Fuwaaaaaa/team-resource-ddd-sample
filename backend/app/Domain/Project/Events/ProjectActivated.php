<?php

declare(strict_types=1);

namespace App\Domain\Project\Events;

use App\Domain\Project\ProjectId;

final class ProjectActivated
{
    public function __construct(private ProjectId $projectId) {}

    public function projectId(): ProjectId
    {
        return $this->projectId;
    }
}
