<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use App\Domain\Allocation\AllocationPercentage;
use App\Domain\Allocation\ResourceAllocation;
use App\Domain\Member\Member;
use App\Domain\Member\MemberId;
use App\Domain\Project\Project;
use App\Domain\Service\AllocationServiceInterface;
use App\Domain\Service\MemberCapacityEntry;
use App\Domain\Service\MemberOverloadEntry;
use App\Domain\Service\OverloadAnalysis;
use App\Domain\Service\ResourceSurplusDeficit;
use App\Domain\Service\SkillGapAnalysis;
use App\Domain\Service\SkillGapEntry;
use App\Domain\Service\SkillGapWarning;
use App\Domain\Service\TeamCapacitySnapshot;
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
                if (!$allocation->skillId()->equals($skillId)) {
                    continue;
                }
                if (!$allocation->isActive() || !$allocation->coversDate($referenceDate)) {
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
                $skillId = new \App\Domain\Skill\SkillId($skillIdStr);
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

                if (!isset($minimumProficiencies[$key])
                    || $requiredSkill->minimumProficiency()->level() > $minimumProficiencies[$key]->level()
                ) {
                    $minimumProficiencies[$key] = $requiredSkill->minimumProficiency();
                }
            }
        }

        // 各スキルについて、チーム内の適格者数をカウント
        $entries = [];
        foreach ($totalRequired as $skillIdStr => $requiredHeadcount) {
            $skillId = new \App\Domain\Skill\SkillId($skillIdStr);
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
        DateTimeImmutable $referenceDate
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

            $standardHours = $member->standardWorkingHours();
            $entries[] = new MemberOverloadEntry(
                $member->id(),
                $standardHours->hoursPerDay(),
                $totalPercentage,
                $standardHours->overloadHours($totalPercentage)
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
                if (!$allocation->skillId()->equals($skillId)) {
                    continue;
                }
                if (!$allocation->isActive() || !$allocation->coversDate($referenceDate)) {
                    continue;
                }

                $member = $memberMap[$allocation->memberId()->toString()] ?? null;
                if ($member === null) {
                    continue;
                }

                $proficiency = $member->proficiencyFor($skillId);
                $actualLevel = $proficiency?->level();

                // スキルを持っていない、または要求水準未満の場合に警告
                if ($proficiency === null || !$proficiency->satisfies($requiredSkill->minimumProficiency())) {
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
}
