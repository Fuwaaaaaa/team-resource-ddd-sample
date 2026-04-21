<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Mappers;

use App\Domain\Member\Member;
use App\Domain\Member\MemberId;
use App\Domain\Member\MemberName;
use App\Domain\Member\MemberSkill;
use App\Domain\Member\MemberSkillId;
use App\Domain\Member\SkillProficiency;
use App\Domain\Member\StandardWorkingHours;
use App\Domain\Skill\SkillId;
use App\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use App\Infrastructure\Persistence\Eloquent\Models\MemberSkillModel;
use ReflectionClass;

/**
 * Member 集約と Eloquent モデル間のマッパー。
 *
 * DBからの再構成は「ドメインイベントを発火させない」ためにリフレクションで private プロパティを直接セットする。
 * Domain の Member クラスは一切変更していない。
 */
final class MemberMapper
{
    /**
     * @param  iterable<MemberSkillModel>  $memberSkillModels
     */
    public static function toDomain(MemberModel $model, iterable $memberSkillModels): Member
    {
        $ref = new ReflectionClass(Member::class);
        /** @var Member $member */
        $member = $ref->newInstanceWithoutConstructor();

        $props = [
            'id' => new MemberId((string) $model->id),
            'name' => new MemberName((string) $model->name),
            'standardWorkingHours' => new StandardWorkingHours((float) $model->standard_working_hours),
            'skills' => [],
            'domainEvents' => [],
        ];

        $skills = [];
        foreach ($memberSkillModels as $ms) {
            $skill = new MemberSkill(
                new MemberSkillId((string) $ms->id),
                new SkillId((string) $ms->skill_id),
                new SkillProficiency((int) $ms->proficiency),
            );
            $skills[(string) $ms->skill_id] = $skill;
        }
        $props['skills'] = $skills;

        foreach ($props as $name => $value) {
            $prop = $ref->getProperty($name);
            $prop->setValue($member, $value);
        }

        return $member;
    }

    /** @return array<string, mixed> */
    public static function toRow(Member $member): array
    {
        return [
            'id' => $member->id()->toString(),
            'name' => $member->name()->toString(),
            'standard_working_hours' => $member->standardWorkingHours()->hoursPerDay(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function skillsToRows(Member $member): array
    {
        $rows = [];
        foreach ($member->skills() as $skill) {
            $rows[] = [
                'id' => $skill->id()->toString(),
                'member_id' => $member->id()->toString(),
                'skill_id' => $skill->skillId()->toString(),
                'proficiency' => $skill->proficiency()->level(),
            ];
        }

        return $rows;
    }
}
