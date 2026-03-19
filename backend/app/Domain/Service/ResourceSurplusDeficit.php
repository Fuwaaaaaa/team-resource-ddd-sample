<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Skill\SkillId;

final class ResourceSurplusDeficit
{
    /** @var SkillGapEntry[] */
    private array $entries;

    /** @param SkillGapEntry[] $entries */
    public function __construct(array $entries)
    {
        $this->entries = $entries;
    }

    /** @return SkillGapEntry[] */
    public function entries(): array
    {
        return $this->entries;
    }

    /** 不足しているスキルのみ取得 */
    public function deficits(): array
    {
        return array_values(array_filter(
            $this->entries,
            fn(SkillGapEntry $entry) => $entry->gap() < 0
        ));
    }

    /** 余剰のスキルのみ取得 */
    public function surpluses(): array
    {
        return array_values(array_filter(
            $this->entries,
            fn(SkillGapEntry $entry) => $entry->gap() > 0
        ));
    }

    public function hasDeficit(): bool
    {
        return count($this->deficits()) > 0;
    }
}
