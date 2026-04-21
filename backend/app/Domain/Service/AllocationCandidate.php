<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Member\MemberId;
use App\Domain\Skill\SkillId;

/**
 * アサインメント推薦候補。
 *
 * score は複合指標: 余剰キャパ × 熟練度余裕 × 経験歴。
 * reasons は UI に表示する「なぜこのメンバーか」の人間向け説明文（日本語）。
 */
final class AllocationCandidate
{
    /** @param string[] $reasons */
    public function __construct(
        private MemberId $memberId,
        private SkillId $skillId,
        private int $proficiency,
        private int $availablePercentage,
        private int $pastProjectExperienceCount,
        private float $score,
        private array $reasons,
    ) {}

    public function memberId(): MemberId
    {
        return $this->memberId;
    }

    public function skillId(): SkillId
    {
        return $this->skillId;
    }

    public function proficiency(): int
    {
        return $this->proficiency;
    }

    public function availablePercentage(): int
    {
        return $this->availablePercentage;
    }

    public function pastProjectExperienceCount(): int
    {
        return $this->pastProjectExperienceCount;
    }

    public function score(): float
    {
        return $this->score;
    }

    /** @return string[] */
    public function reasons(): array
    {
        return $this->reasons;
    }
}
