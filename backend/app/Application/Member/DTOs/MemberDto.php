<?php

declare(strict_types=1);

namespace App\Application\Member\DTOs;

use App\Domain\Member\Member;

final class MemberDto
{
    /**
     * @param  array<int, array{id:string, skillId:string, proficiency:int}>  $skills
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly float $standardWorkingHours,
        public readonly array $skills,
    ) {}

    public static function fromDomain(Member $member): self
    {
        $skills = [];
        foreach ($member->skills() as $s) {
            $skills[] = [
                'id' => $s->id()->toString(),
                'skillId' => $s->skillId()->toString(),
                'proficiency' => $s->proficiency()->level(),
            ];
        }

        return new self(
            id: $member->id()->toString(),
            name: $member->name()->toString(),
            standardWorkingHours: $member->standardWorkingHours()->hoursPerDay(),
            skills: $skills,
        );
    }
}
