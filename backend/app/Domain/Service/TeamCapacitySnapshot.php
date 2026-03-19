<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Member\MemberId;
use App\Domain\Skill\SkillId;

final class TeamCapacitySnapshot
{
    /** @var MemberCapacityEntry[] */
    private array $entries;

    /** @param MemberCapacityEntry[] $entries */
    public function __construct(array $entries)
    {
        $this->entries = $entries;
    }

    /** @return MemberCapacityEntry[] */
    public function entries(): array
    {
        return $this->entries;
    }

    public function findByMemberId(MemberId $memberId): ?MemberCapacityEntry
    {
        foreach ($this->entries as $entry) {
            if ($entry->memberId()->equals($memberId)) {
                return $entry;
            }
        }
        return null;
    }
}
