<?php

declare(strict_types=1);

namespace App\Application\Member\Queries;

use App\Application\Member\DTOs\MemberKpiDto;
use App\Domain\Allocation\ResourceAllocationRepositoryInterface;
use App\Domain\Member\MemberId;
use App\Domain\Member\MemberRepositoryInterface;
use App\Domain\Project\ProjectRepositoryInterface;
use App\Domain\Skill\SkillRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * メンバー別 KPI を算出する。
 *
 * - 現在稼働率 (基準日に有効なアクティブ allocation の percentage 合計)
 * - 残キャパ (100 - 稼働率、負値は 0 にクランプ)
 * - 過負荷フラグ (稼働率 > 100)
 * - アクティブ allocation 一覧 (project名 / skill名 / 期間 / 残日数)
 * - 30 日以内に終了する allocation
 * - 保有スキル一覧 (proficiency 付き)
 */
final class GetMemberKpiHandler
{
    public function __construct(
        private MemberRepositoryInterface $memberRepository,
        private ResourceAllocationRepositoryInterface $allocationRepository,
        private ProjectRepositoryInterface $projectRepository,
        private SkillRepositoryInterface $skillRepository,
    ) {}

    public function handle(GetMemberKpiQuery $query): MemberKpiDto
    {
        $memberId = new MemberId($query->memberId);
        $member = $this->memberRepository->findById($memberId);
        if ($member === null) {
            throw new InvalidArgumentException("Member not found: {$query->memberId}");
        }

        $referenceDate = new DateTimeImmutable($query->referenceDate);
        $allocations = $this->allocationRepository->findByMemberId($memberId);

        // プロジェクト名とスキル名の解決マップ
        $projectNames = [];
        foreach ($this->projectRepository->findAll() as $p) {
            $projectNames[$p->id()->toString()] = $p->name()->toString();
        }
        $skillNames = [];
        foreach ($this->skillRepository->findAll() as $s) {
            $skillNames[$s->id()->toString()] = $s->name()->toString();
        }

        $currentUtilization = 0;
        $activeAllocations = [];
        $upcomingEnds = [];
        foreach ($allocations as $a) {
            if (! $a->isActive() || ! $a->coversDate($referenceDate)) {
                continue;
            }
            $currentUtilization += $a->percentage()->value();

            $end = $a->period()->endDate();
            $daysRemaining = (int) $referenceDate->diff($end)->days;
            $projectIdStr = $a->projectId()->toString();
            $skillIdStr = $a->skillId()->toString();

            $activeAllocations[] = [
                'allocationId' => $a->id()->toString(),
                'projectId' => $projectIdStr,
                'projectName' => $projectNames[$projectIdStr] ?? 'Unknown',
                'skillId' => $skillIdStr,
                'skillName' => $skillNames[$skillIdStr] ?? $skillIdStr,
                'percentage' => $a->percentage()->value(),
                'startDate' => $a->period()->startDate()->format('Y-m-d'),
                'endDate' => $end->format('Y-m-d'),
                'daysRemaining' => $daysRemaining,
            ];

            if ($daysRemaining <= 30) {
                $upcomingEnds[] = [
                    'allocationId' => $a->id()->toString(),
                    'projectId' => $projectIdStr,
                    'projectName' => $projectNames[$projectIdStr] ?? 'Unknown',
                    'daysRemaining' => $daysRemaining,
                    'endDate' => $end->format('Y-m-d'),
                ];
            }
        }

        // daysRemaining の昇順
        usort($upcomingEnds, fn ($a, $b) => $a['daysRemaining'] <=> $b['daysRemaining']);
        usort($activeAllocations, fn ($a, $b) => $a['daysRemaining'] <=> $b['daysRemaining']);

        // 保有スキル
        $skills = [];
        foreach ($member->skills() as $ms) {
            $skillIdStr = $ms->skillId()->toString();
            $skills[] = [
                'skillId' => $skillIdStr,
                'skillName' => $skillNames[$skillIdStr] ?? $skillIdStr,
                'proficiency' => $ms->proficiency()->level(),
            ];
        }
        usort($skills, fn ($a, $b) => $b['proficiency'] <=> $a['proficiency']);

        $remainingCapacity = max(0, 100 - $currentUtilization);

        return new MemberKpiDto(
            memberId: $member->id()->toString(),
            memberName: $member->name()->toString(),
            referenceDate: $query->referenceDate,
            currentUtilization: $currentUtilization,
            remainingCapacity: $remainingCapacity,
            isOverloaded: $currentUtilization > 100,
            activeAllocationCount: count($activeAllocations),
            activeAllocations: $activeAllocations,
            upcomingEnds: $upcomingEnds,
            skills: $skills,
        );
    }
}
