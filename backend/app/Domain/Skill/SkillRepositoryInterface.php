<?php

declare(strict_types=1);

namespace App\Domain\Skill;

interface SkillRepositoryInterface
{
    public function findById(SkillId $id): ?Skill;

    /** @return Skill[] */
    public function findAll(): array;

    public function save(Skill $skill): void;

    public function nextIdentity(): SkillId;
}
