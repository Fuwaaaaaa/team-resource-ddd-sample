<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Skill\Skill;
use App\Domain\Skill\SkillId;
use App\Domain\Skill\SkillRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Mappers\SkillMapper;
use App\Infrastructure\Persistence\Eloquent\Models\SkillModel;
use Illuminate\Support\Str;

final class EloquentSkillRepository implements SkillRepositoryInterface
{
    public function findById(SkillId $id): ?Skill
    {
        $model = SkillModel::find($id->toString());
        return $model ? SkillMapper::toDomain($model) : null;
    }

    /** @return Skill[] */
    public function findAll(): array
    {
        return SkillModel::orderBy('name')->get()
            ->map(fn (SkillModel $m) => SkillMapper::toDomain($m))
            ->all();
    }

    public function save(Skill $skill): void
    {
        SkillModel::updateOrCreate(
            ['id' => $skill->id()->toString()],
            [
                'name' => $skill->name()->toString(),
                'category' => $skill->category()->toString(),
            ],
        );
    }

    public function delete(SkillId $id): void
    {
        SkillModel::where('id', $id->toString())->delete();
    }

    public function nextIdentity(): SkillId
    {
        return new SkillId((string) Str::uuid7());
    }
}
