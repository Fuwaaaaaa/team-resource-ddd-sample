<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use App\Domain\Allocation\AllocationPercentage;
use App\Domain\Member\MemberId;
use App\Domain\Project\Project;
use App\Domain\Project\ProjectId;
use App\Domain\Service\AllocationCandidate;
use App\Domain\Service\AllocationServiceInterface;
use App\Domain\Service\MemberCapacityEntry;
use App\Domain\Service\MemberOverloadEntry;
use App\Domain\Service\OverloadAnalysis;
use App\Domain\Service\ResourceSurplusDeficit;
use App\Domain\Service\SkillGapAnalysis;
use App\Domain\Service\SkillGapEntry;
use App\Domain\Service\SkillGapWarning;
use App\Domain\Service\TeamCapacitySnapshot;
use App\Domain\Skill\SkillId;
use DateTimeImmutable;

final class AllocationService implements AllocationServiceInterface
{
    public function calculateSurplusDeficit(
        Project $project,
        array $allocations,
        array $members,
        DateTimeImmutable $referenceDate
    ): ResourceSurplusDeficit {
        // メンバーをIDでインデックス化
        $memberMap = [];
        foreach ($members as $member) {
            $memberMap[$member->id()->toString()] = $member;
        }

        $entries = [];
        foreach ($project->requiredSkills() as $requiredSkill) {
            $skillId = $requiredSkill->skillId();
            $qualifiedCount = 0;

            foreach ($allocations as $allocation) {
                // このスキル・このプロジェクト・この日付に該当するアロケーションのみ
                if (! $allocation->skillId()->equals($skillId)) {
                    continue;
                }
                if (! $allocation->isActive() || ! $allocation->coversDate($referenceDate)) {
                    continue;
                }

                $member = $memberMap[$allocation->memberId()->toString()] ?? null;
                if ($member === null) {
                    continue;
                }

                $proficiency = $member->proficiencyFor($skillId);
                if ($proficiency !== null && $proficiency->satisfies($requiredSkill->minimumProficiency())) {
                    $qualifiedCount++;
                }
            }

            $entries[] = new SkillGapEntry(
                $skillId,
                $requiredSkill->headcount(),
                $qualifiedCount
            );
        }

        return new ResourceSurplusDeficit($entries);
    }

    public function buildTeamCapacitySnapshot(
        array $members,
        array $allocations,
        DateTimeImmutable $referenceDate
    ): TeamCapacitySnapshot {
        // 全スキルIDを収集
        $allSkillIds = [];
        foreach ($members as $member) {
            foreach ($member->skills() as $memberSkill) {
                $allSkillIds[$memberSkill->skillId()->toString()] = true;
            }
        }

        $entries = [];
        foreach ($members as $member) {
            // このメンバーの基準日時点の割り当て工数合計
            $usedPercentage = 0;
            foreach ($allocations as $allocation) {
                if ($allocation->memberId()->equals($member->id())
                    && $allocation->isActive()
                    && $allocation->coversDate($referenceDate)
                ) {
                    $usedPercentage += $allocation->percentage()->value();
                }
            }
            $available = max(0, 100 - $usedPercentage);

            // スキル別の熟練度マップ
            $skillProficiencies = [];
            foreach (array_keys($allSkillIds) as $skillIdStr) {
                $skillId = new SkillId($skillIdStr);
                $proficiency = $member->proficiencyFor($skillId);
                $skillProficiencies[$skillIdStr] = $proficiency?->level();
            }

            $entries[] = new MemberCapacityEntry(
                $member->id(),
                $available,
                $skillProficiencies
            );
        }

        return new TeamCapacitySnapshot($entries);
    }

    public function canAllocate(
        MemberId $memberId,
        AllocationPercentage $requestedPercentage,
        array $existingAllocations,
        DateTimeImmutable $referenceDate
    ): bool {
        $usedPercentage = 0;
        foreach ($existingAllocations as $allocation) {
            if ($allocation->memberId()->equals($memberId)
                && $allocation->isActive()
                && $allocation->coversDate($referenceDate)
            ) {
                $usedPercentage += $allocation->percentage()->value();
            }
        }

        return ($usedPercentage + $requestedPercentage->value()) <= 100;
    }

    public function analyzeSkillGaps(
        array $projects,
        array $members,
        array $allocations,
        DateTimeImmutable $referenceDate
    ): SkillGapAnalysis {
        // 全プロジェクトのRequiredSkillをスキルID別に集約
        $totalRequired = []; // skillIdStr => totalHeadcount
        $minimumProficiencies = []; // skillIdStr => RequiredProficiency (最大の要求水準を採用)

        foreach ($projects as $project) {
            foreach ($project->requiredSkills() as $requiredSkill) {
                $key = $requiredSkill->skillId()->toString();
                $totalRequired[$key] = ($totalRequired[$key] ?? 0) + $requiredSkill->headcount();

                if (! isset($minimumProficiencies[$key])
                    || $requiredSkill->minimumProficiency()->level() > $minimumProficiencies[$key]->level()
                ) {
                    $minimumProficiencies[$key] = $requiredSkill->minimumProficiency();
                }
            }
        }

        // 各スキルについて、チーム内の適格者数をカウント
        $entries = [];
        foreach ($totalRequired as $skillIdStr => $requiredHeadcount) {
            $skillId = new SkillId($skillIdStr);
            $requiredProficiency = $minimumProficiencies[$skillIdStr];
            $qualifiedCount = 0;

            foreach ($members as $member) {
                $proficiency = $member->proficiencyFor($skillId);
                if ($proficiency !== null && $proficiency->satisfies($requiredProficiency)) {
                    // このメンバーに空きキャパシティがあるかもチェック
                    $usedPercentage = 0;
                    foreach ($allocations as $allocation) {
                        if ($allocation->memberId()->equals($member->id())
                            && $allocation->isActive()
                            && $allocation->coversDate($referenceDate)
                        ) {
                            $usedPercentage += $allocation->percentage()->value();
                        }
                    }
                    if ($usedPercentage < 100) {
                        $qualifiedCount++;
                    }
                }
            }

            $entries[] = new SkillGapEntry($skillId, $requiredHeadcount, $qualifiedCount);
        }

        return new SkillGapAnalysis($entries);
    }

    public function detectOverload(
        array $members,
        array $allocations,
        DateTimeImmutable $referenceDate,
        array $absences = []
    ): OverloadAnalysis {
        $entries = [];

        foreach ($members as $member) {
            $totalPercentage = 0;
            foreach ($allocations as $allocation) {
                if ($allocation->memberId()->equals($member->id())
                    && $allocation->isActive()
                    && $allocation->coversDate($referenceDate)
                ) {
                    $totalPercentage += $allocation->percentage()->value();
                }
            }

            // 基準日にこのメンバーが不在なら実効稼働時間は 0
            $onAbsence = false;
            foreach ($absences as $absence) {
                if ($absence->memberId()->equals($member->id())
                    && $absence->coversDate($referenceDate)
                ) {
                    $onAbsence = true;
                    break;
                }
            }

            $standardHours = $member->standardWorkingHours();

            if ($onAbsence) {
                // 不在日: 稼働可能時間 0、割当があれば全部過負荷扱い
                $effectiveHoursPerDay = 0.0;
                $overloadHours = $standardHours->hoursPerDay() * ($totalPercentage / 100.0);
            } else {
                $effectiveHoursPerDay = $standardHours->hoursPerDay();
                $overloadHours = $standardHours->overloadHours($totalPercentage);
            }

            $entries[] = new MemberOverloadEntry(
                $member->id(),
                $effectiveHoursPerDay,
                $totalPercentage,
                $overloadHours
            );
        }

        return new OverloadAnalysis($entries);
    }

    public function detectSkillGapWarnings(
        Project $project,
        array $allocations,
        array $members,
        DateTimeImmutable $referenceDate
    ): array {
        $memberMap = [];
        foreach ($members as $member) {
            $memberMap[$member->id()->toString()] = $member;
        }

        $warnings = [];

        foreach ($project->requiredSkills() as $requiredSkill) {
            $skillId = $requiredSkill->skillId();
            $requiredLevel = $requiredSkill->minimumProficiency()->level();

            foreach ($allocations as $allocation) {
                if (! $allocation->skillId()->equals($skillId)) {
                    continue;
                }
                if (! $allocation->isActive() || ! $allocation->coversDate($referenceDate)) {
                    continue;
                }

                $member = $memberMap[$allocation->memberId()->toString()] ?? null;
                if ($member === null) {
                    continue;
                }

                $proficiency = $member->proficiencyFor($skillId);
                $actualLevel = $proficiency?->level();

                // スキルを持っていない、または要求水準未満の場合に警告
                if ($proficiency === null || ! $proficiency->satisfies($requiredSkill->minimumProficiency())) {
                    $warnings[] = new SkillGapWarning(
                        $member->id(),
                        $project->id(),
                        $skillId,
                        $requiredLevel,
                        $actualLevel
                    );
                }
            }
        }

        return $warnings;
    }

    public function suggestCandidates(
        SkillId $skillId,
        int $minimumProficiency,
        ProjectId $projectId,
        DateTimeImmutable $periodStart,
        array $members,
        array $allocations,
        int $limit = 5
    ): array {
        $candidates = [];

        foreach ($members as $member) {
            $proficiency = $member->proficiencyFor($skillId);
            if ($proficiency === null || $proficiency->level() < $minimumProficiency) {
                continue; // 熟練度不足は候補外
            }

            // period 開始日の余剰キャパ
            $used = 0;
            $pastOnProject = 0;
            foreach ($allocations as $alloc) {
                if (! $alloc->memberId()->equals($member->id())) {
                    continue;
                }
                if ($alloc->isActive() && $alloc->coversDate($periodStart)) {
                    $used += $alloc->percentage()->value();
                }
                // revoked 含め同プロジェクト経験歴を数える
                if ($alloc->projectId()->equals($projectId)) {
                    $pastOnProject++;
                }
            }
            $available = max(0, 100 - $used);
            if ($available === 0) {
                continue; // キャパ 0 は候補外
            }

            // スコア: 余剰キャパ (0-100) + 熟練度余裕 × 10 + 経験歴 × 5
            $proficiencyBonus = ($proficiency->level() - $minimumProficiency) * 10;
            $experienceBonus = min($pastOnProject, 3) * 5; // 過剰経験には逓減
            $score = (float) $available + $proficiencyBonus + $experienceBonus;

            $reasons = [];
            $reasons[] = sprintf('熟練度 L%d (要求 L%d)', $proficiency->level(), $minimumProficiency);
            $reasons[] = sprintf('空きキャパ %d%%', $available);
            if ($pastOnProject > 0) {
                $reasons[] = sprintf('同プロジェクト経験 %d 件', $pastOnProject);
            }

            $candidates[] = new AllocationCandidate(
                memberId: $member->id(),
                skillId: $skillId,
                proficiency: $proficiency->level(),
                availablePercentage: $available,
                pastProjectExperienceCount: $pastOnProject,
                score: $score,
                reasons: $reasons,
            );
        }

        usort($candidates, fn (AllocationCandidate $a, AllocationCandidate $b) => $b->score() <=> $a->score());

        return array_slice($candidates, 0, $limit);
    }
}
