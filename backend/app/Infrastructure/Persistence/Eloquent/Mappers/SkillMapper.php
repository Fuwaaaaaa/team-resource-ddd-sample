<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Mappers;

use App\Domain\Skill\Skill;
use App\Domain\Skill\SkillCategory;
use App\Domain\Skill\SkillId;
use App\Domain\Skill\SkillName;
use App\Infrastructure\Persistence\Eloquent\Models\SkillModel;

final class SkillMapper
{
    public static function toDomain(SkillModel $model): Skill
    {
        return new Skill(
            new SkillId((string) $model->id),
            new SkillName((string) $model->name),
            new SkillCategory((string) $model->category),
        );
    }

    /** @return array<string, string> */
    public static function toRow(Skill $skill): array
    {
        return [
            'id' => $skill->id()->toString(),
            'name' => $skill->name()->toString(),
            'category' => $skill->category()->toString(),
        ];
    }
}
