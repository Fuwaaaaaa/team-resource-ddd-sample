<?php

declare(strict_types=1);

namespace App\Domain\Project;

use App\Domain\Skill\SkillId;

final class Project
{
    private ProjectId $id;

    private ProjectName $name;

    /** @var array<string, RequiredSkill> SkillId文字列でキー */
    private array $requiredSkills = [];

    /** @var object[] */
    private array $domainEvents = [];

    public function __construct(ProjectId $id, ProjectName $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    public function id(): ProjectId
    {
        return $this->id;
    }

    public function name(): ProjectName
    {
        return $this->name;
    }

    /** @return RequiredSkill[] */
    public function requiredSkills(): array
    {
        return array_values($this->requiredSkills);
    }

    public function addOrUpdateRequirement(
        RequiredSkillId $requiredSkillId,
        SkillId $skillId,
        RequiredProficiency $minimumProficiency,
        int $headcount
    ): void {
        $key = $skillId->toString();
        $this->requiredSkills[$key] = new RequiredSkill(
            $requiredSkillId,
            $skillId,
            $minimumProficiency,
            $headcount
        );
        $this->domainEvents[] = new Events\ProjectRequirementChanged($this->id, $skillId);
    }

    public function requirementFor(SkillId $skillId): ?RequiredSkill
    {
        $key = $skillId->toString();

        return $this->requiredSkills[$key] ?? null;
    }

    /** @return object[] */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }
}
