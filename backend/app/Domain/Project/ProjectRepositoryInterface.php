<?php

declare(strict_types=1);

namespace App\Domain\Project;

interface ProjectRepositoryInterface
{
    public function findById(ProjectId $id): ?Project;

    /** @return Project[] */
    public function findAll(): array;

    public function save(Project $project): void;

    public function nextIdentity(): ProjectId;
}
