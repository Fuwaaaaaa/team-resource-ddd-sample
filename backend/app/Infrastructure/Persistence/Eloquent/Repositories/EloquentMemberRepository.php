<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Member\Member;
use App\Domain\Member\MemberId;
use App\Domain\Member\MemberRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Mappers\MemberMapper;
use App\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use App\Infrastructure\Persistence\Eloquent\Models\MemberSkillModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentMemberRepository implements MemberRepositoryInterface
{
    public function findById(MemberId $id): ?Member
    {
        $model = MemberModel::with('skills')->find($id->toString());
        return $model ? MemberMapper::toDomain($model, $model->skills) : null;
    }

    /** @return Member[] */
    public function findAll(): array
    {
        return MemberModel::with('skills')->orderBy('name')->get()
            ->map(fn (MemberModel $m) => MemberMapper::toDomain($m, $m->skills))
            ->all();
    }

    /** @param MemberId[] $ids @return Member[] */
    public function findByIds(array $ids): array
    {
        $keys = array_map(fn (MemberId $id) => $id->toString(), $ids);
        return MemberModel::with('skills')->whereIn('id', $keys)->get()
            ->map(fn (MemberModel $m) => MemberMapper::toDomain($m, $m->skills))
            ->all();
    }

    public function save(Member $member): void
    {
        DB::transaction(function () use ($member): void {
            MemberModel::updateOrCreate(
                ['id' => $member->id()->toString()],
                MemberMapper::toRow($member),
            );

            $memberId = $member->id()->toString();
            $keepIds = [];
            foreach (MemberMapper::skillsToRows($member) as $row) {
                MemberSkillModel::updateOrCreate(
                    ['id' => $row['id']],
                    $row,
                );
                $keepIds[] = $row['id'];
            }
            MemberSkillModel::where('member_id', $memberId)
                ->whereNotIn('id', $keepIds)
                ->delete();
        });
    }

    public function delete(MemberId $id): void
    {
        MemberModel::where('id', $id->toString())->delete();
    }

    public function nextIdentity(): MemberId
    {
        return new MemberId((string) Str::uuid7());
    }
}
