<?php

declare(strict_types=1);

namespace App\Application\Dashboard\Queries;

use App\Application\Dashboard\DTOs\SkillGapWarningDto;
use App\Application\Dashboard\DTOs\SkillGapWarningListDto;
use App\Domain\Allocation\ResourceAllocationRepositoryInterface;
use App\Domain\Member\MemberRepositoryInterface;
use App\Domain\Project\ProjectId;
use App\Domain\Project\ProjectRepositoryInterface;
use App\Domain\Service\AllocationServiceInterface;
use App\Domain\Skill\SkillRepositoryInterface;
use DateTimeImmutable;

final class GetSkillGapWarningsHandler
{
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
        private MemberRepositoryInterface $memberRepository,
        private ResourceAllocationRepositoryInterface $allocationRepository,
        private AllocationServiceInterface $allocationService,
        private SkillRepositoryInterface $skillRepository,
    ) {
    }

    public function handle(GetSkillGapWarningsQuery $query): SkillGapWarningListDto
    {
        $referenceDate = new DateTimeImmutable($query->referenceDate);

        // プロジェクト取得（単体 or 全件）
        if ($query->projectId !== null) {
            $project = $this->projectRepository->findById(new ProjectId($query->projectId));
            $projects = $project !== null ? [$project] : [];
        } else {
            $projects = $this->projectRepository->findAll();
        }

        $members = $this->memberRepository->findAll();
        $memberMap = [];
        foreach ($members as $member) {
            $memberMap[$member->id()->toString()] = $member;
        }

        // 各プロジェクトについてスキル不足警告を収集
        $warningDtos = [];
        foreach ($projects as $project) {
            $allocations = $this->allocationRepository->findByProjectId($project->id());

            $warnings = $this->allocationService->detectSkillGapWarnings(
                $project,
                $allocations,
                $members,
                $referenceDate
            );

            foreach ($warnings as $warning) {
                $member = $memberMap[$warning->memberId()->toString()];
                $skill = $this->skillRepository->findById($warning->skillId());

                $warningDtos[] = new SkillGapWarningDto(
                    memberId: $warning->memberId()->toString(),
                    memberName: $member->name()->toString(),
                    projectId: $warning->projectId()->toString(),
                    projectName: $project->name()->toString(),
                    skillId: $warning->skillId()->toString(),
                    skillName: $skill?->name()->toString() ?? 'Unknown',
                    requiredLevel: $warning->requiredLevel(),
                    actualLevel: $warning->actualLevel(),
                    deficitLevel: $warning->deficitLevel(),
                );
            }
        }

        return new SkillGapWarningListDto(
            warnings: $warningDtos,
            totalWarnings: count($warningDtos),
            referenceDate: $query->referenceDate,
        );
    }
}
