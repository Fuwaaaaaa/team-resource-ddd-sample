<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Member\MemberId;
use App\Domain\Skill\SkillId;

final class MemberCapacityEntry
{
    private MemberId $memberId;
    private int $availablePercentage;
    /** @var array<string, int|null> SkillId文字列 => 熟練度（null=スキルなし） */
    private array $skillProficiencies;

    /** @param array<string, int|null> $skillProficiencies */
    public function __construct(MemberId $memberId, int $availablePercentage, array $skillProficiencies)
    {
        $this->memberId = $memberId;
        $this->availablePercentage = $availablePercentage;
        $this->skillProficiencies = $skillProficiencies;
    }

    public function memberId(): MemberId
    {
        return $this->memberId;
    }

    /** 未割り当ての工数（0-100） */
    public function availablePercentage(): int
    {
        return $this->availablePercentage;
    }

    /** @return array<string, int|null> */
    public function skillProficiencies(): array
    {
        return $this->skillProficiencies;
    }

    public function proficiencyFor(SkillId $skillId): ?int
    {
        return $this->skillProficiencies[$skillId->toString()] ?? null;
    }
}
