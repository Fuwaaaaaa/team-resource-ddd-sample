<?php

declare(strict_types=1);

namespace App\Application\Dashboard\Queries;

use App\Application\Dashboard\DTOs\CapacityForecastDto;
use App\Application\Dashboard\DTOs\ForecastBucketDto;
use App\Application\Dashboard\DTOs\SkillForecastDto;
use App\Domain\Allocation\ResourceAllocationRepositoryInterface;
use App\Domain\Member\MemberRepositoryInterface;
use App\Domain\Project\ProjectRepositoryInterface;
use App\Domain\Skill\SkillRepositoryInterface;
use DateTimeImmutable;

/**
 * スキル別に月次の需給ギャップを予測する。
 *
 * - 需要: active/planning プロジェクトの required_skills.headcount を skill 別に合計。
 *         プロジェクトに計画期間 (planned_start_date/planned_end_date) が設定されている場合は
 *         その月と重なるバケットのみに寄せる。期間未設定のプロジェクトは全バケットに寄せる (後方互換)。
 * - 供給: 各月 15 日時点で該当スキルを保有するメンバーの残キャパ合計 (100% = 1 名換算)。
 * - severity: gap >= 0 → ok / -1 < gap < 0 → watch / gap <= -1 → critical。
 */
final class GetCapacityForecastHandler
{
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
        private MemberRepositoryInterface $memberRepository,
        private ResourceAllocationRepositoryInterface $allocationRepository,
        private SkillRepositoryInterface $skillRepository,
    ) {}

    public function handle(GetCapacityForecastQuery $query): CapacityForecastDto
    {
        $reference = new DateTimeImmutable($query->referenceDate);
        $monthsAhead = $query->monthsAhead;

        $members = $this->memberRepository->findAll();
        $projects = array_values(array_filter(
            $this->projectRepository->findAll(),
            fn ($p) => $p->status()->countsForCapacity(),
        ));
        $skills = $this->skillRepository->findAll();
        $skillNameById = [];
        foreach ($skills as $skill) {
            $skillNameById[$skill->id()->toString()] = $skill->name()->toString();
        }

        $buckets = [];
        for ($i = 0; $i < $monthsAhead; $i++) {
            $monthStart = $this->addMonths($this->firstOfMonth($reference), $i);
            $monthEnd = $this->addMonths($monthStart, 1)->modify('-1 day');
            $probe = $monthStart->modify('+14 days'); // 月中 (15 日)
            $monthLabel = $monthStart->format('Y-m');

            // 需要: 当該月と重なる計画期間を持つ (or 期間未設定の) プロジェクトの required_skills を集計
            /** @var array<string,int> $demand */
            $demand = [];
            foreach ($projects as $project) {
                if (! $project->overlapsMonth($monthStart, $monthEnd)) {
                    continue;
                }
                foreach ($project->requiredSkills() as $req) {
                    $key = $req->skillId()->toString();
                    $demand[$key] = ($demand[$key] ?? 0) + $req->headcount();
                }
            }

            // 供給: 該当月 15 日時点 active allocation から残キャパ算出
            $activeOnProbe = $this->allocationRepository->findActiveOnDate($probe);

            $usedByMember = [];
            foreach ($activeOnProbe as $a) {
                $mid = $a->memberId()->toString();
                $usedByMember[$mid] = ($usedByMember[$mid] ?? 0) + $a->percentage()->value();
            }

            /** @var array<string,float> $supply */
            $supply = [];
            foreach ($members as $member) {
                $used = $usedByMember[$member->id()->toString()] ?? 0;
                $available = max(0, 100 - $used);
                foreach ($member->skills() as $memberSkill) {
                    $key = $memberSkill->skillId()->toString();
                    $supply[$key] = ($supply[$key] ?? 0.0) + ($available / 100.0);
                }
            }

            // 需要のあるスキルのみ出力
            $skillDtos = [];
            foreach ($demand as $skillIdStr => $demandHeadcount) {
                $supplyEq = round($supply[$skillIdStr] ?? 0.0, 2);
                $gap = round($supplyEq - $demandHeadcount, 2);
                $skillDtos[] = new SkillForecastDto(
                    skillId: $skillIdStr,
                    skillName: $skillNameById[$skillIdStr] ?? '',
                    demandHeadcount: $demandHeadcount,
                    supplyHeadcountEquivalent: $supplyEq,
                    gap: $gap,
                    severity: $this->classify($gap),
                );
            }
            usort($skillDtos, fn (SkillForecastDto $a, SkillForecastDto $b) => strcmp($a->skillName, $b->skillName));

            $buckets[] = new ForecastBucketDto(month: $monthLabel, skills: $skillDtos);
        }

        return new CapacityForecastDto(
            referenceDate: $query->referenceDate,
            monthsAhead: $monthsAhead,
            buckets: $buckets,
        );
    }

    private function firstOfMonth(DateTimeImmutable $date): DateTimeImmutable
    {
        return $date->setDate((int) $date->format('Y'), (int) $date->format('m'), 1)
            ->setTime(0, 0, 0);
    }

    private function addMonths(DateTimeImmutable $date, int $months): DateTimeImmutable
    {
        return $date->modify(sprintf('+%d months', $months));
    }

    private function classify(float $gap): string
    {
        if ($gap >= 0.0) {
            return 'ok';
        }
        if ($gap > -1.0) {
            return 'watch';
        }

        return 'critical';
    }
}
