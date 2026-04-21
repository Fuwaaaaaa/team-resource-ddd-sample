<?php

declare(strict_types=1);

namespace App\Domain\Member;

use App\Domain\Skill\SkillId;

final class Member
{
    private MemberId $id;

    private MemberName $name;

    private StandardWorkingHours $standardWorkingHours;

    /** @var array<string, MemberSkill> SkillId文字列でキー */
    private array $skills = [];

    /** @var object[] */
    private array $domainEvents = [];

    public function __construct(
        MemberId $id,
        MemberName $name,
        ?StandardWorkingHours $standardWorkingHours = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->standardWorkingHours = $standardWorkingHours ?? new StandardWorkingHours(8.0);
    }

    public function id(): MemberId
    {
        return $this->id;
    }

    public function name(): MemberName
    {
        return $this->name;
    }

    public function standardWorkingHours(): StandardWorkingHours
    {
        return $this->standardWorkingHours;
    }

    public function updateStandardWorkingHours(StandardWorkingHours $hours): void
    {
        $this->standardWorkingHours = $hours;
    }

    /** @return MemberSkill[] */
    public function skills(): array
    {
        return array_values($this->skills);
    }

    public function addOrUpdateSkill(
        MemberSkillId $memberSkillId,
        SkillId $skillId,
        SkillProficiency $proficiency
    ): void {
        $key = $skillId->toString();
        if (isset($this->skills[$key])) {
            $this->skills[$key]->updateProficiency($proficiency);
        } else {
            $this->skills[$key] = new MemberSkill($memberSkillId, $skillId, $proficiency);
        }
        $this->domainEvents[] = new Events\MemberSkillUpdated($this->id, $skillId, $proficiency);
    }

    public function proficiencyFor(SkillId $skillId): ?SkillProficiency
    {
        $key = $skillId->toString();

        return isset($this->skills[$key])
            ? $this->skills[$key]->proficiency()
            : null;
    }

    /** @return object[] */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }
}
