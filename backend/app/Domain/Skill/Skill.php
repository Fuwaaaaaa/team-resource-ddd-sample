<?php

declare(strict_types=1);

namespace App\Domain\Skill;

final class Skill
{
    private SkillId $id;
    private SkillName $name;
    private SkillCategory $category;

    public function __construct(SkillId $id, SkillName $name, SkillCategory $category)
    {
        $this->id = $id;
        $this->name = $name;
        $this->category = $category;
    }

    public function id(): SkillId
    {
        return $this->id;
    }

    public function name(): SkillName
    {
        return $this->name;
    }

    public function category(): SkillCategory
    {
        return $this->category;
    }

    public function rename(SkillName $newName): void
    {
        $this->name = $newName;
    }
}
