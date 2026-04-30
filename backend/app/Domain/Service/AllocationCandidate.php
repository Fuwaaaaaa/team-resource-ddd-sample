<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Member\MemberId;
use App\Domain\Skill\SkillId;

/**
 * アサインメント推薦候補。
 *
 * score は複合指標: capacityScore + proficiencyScore + experienceScore。
 * 内訳を別フィールドで保持しているので、UI 側で「なぜこのスコアか」を分解表示できる。
 *
 * nextWeekConflict は periodStart の翌週時点で他案件の合計負荷が 100% 以上に達しているか。
 * true のときは候補として残るが UI で警告を出す (新しい allocation を載せると確実に過負荷)。
 *
 * reasons は人間向け説明文 (日本語)。新フィールドと並行して保持し、既存 consumer の互換性を守る。
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
        private float $capacityScore,
        private float $proficiencyScore,
        private float $experienceScore,
        private bool $nextWeekConflict,
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

    public function capacityScore(): float
    {
        return $this->capacityScore;
    }

    public function proficiencyScore(): float
    {
        return $this->proficiencyScore;
    }

    public function experienceScore(): float
    {
        return $this->experienceScore;
    }

    public function hasNextWeekConflict(): bool
    {
        return $this->nextWeekConflict;
    }

    /** @return string[] */
    public function reasons(): array
    {
        return $this->reasons;
    }
}
