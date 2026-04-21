<?php

declare(strict_types=1);

namespace App\Application\Project\DTOs;

use App\Domain\Project\Project;

final class ProjectDto
{
    /**
     * @param  array<int, array{id:string, skillId:string, requiredProficiency:int, headcount:int}>  $requiredSkills
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $status,
        public readonly array $requiredSkills,
    ) {}

    public static function fromDomain(Project $project): self
    {
        $required = [];
        foreach ($project->requiredSkills() as $rs) {
            $required[] = [
                'id' => $rs->id()->toString(),
                'skillId' => $rs->skillId()->toString(),
                'requiredProficiency' => $rs->minimumProficiency()->level(),
                'headcount' => $rs->headcount(),
            ];
        }

        return new self(
            id: $project->id()->toString(),
            name: $project->name()->toString(),
            status: $project->status()->value,
            requiredSkills: $required,
        );
    }
}
