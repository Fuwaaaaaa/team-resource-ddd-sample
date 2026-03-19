<?php

declare(strict_types=1);

namespace App\Domain\Service;

final class SkillGapAnalysis
{
    /** @var SkillGapEntry[] 不足度が大きい順にソート */
    private array $entries;

    /** @param SkillGapEntry[] $entries */
    public function __construct(array $entries)
    {
        // 不足度が大きい順（gap昇順 = 最もdeficitが大きいものが先）
        usort($entries, fn(SkillGapEntry $a, SkillGapEntry $b) => $a->gap() <=> $b->gap());
        $this->entries = $entries;
    }

    /** @return SkillGapEntry[] */
    public function entries(): array
    {
        return $this->entries;
    }

    /** 不足しているスキルのみ */
    public function criticalGaps(): array
    {
        return array_values(array_filter(
            $this->entries,
            fn(SkillGapEntry $entry) => $entry->gap() < 0
        ));
    }

    public function hasCriticalGaps(): bool
    {
        return count($this->criticalGaps()) > 0;
    }
}
