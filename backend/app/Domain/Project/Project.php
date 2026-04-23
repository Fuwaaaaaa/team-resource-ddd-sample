<?php

declare(strict_types=1);

namespace App\Domain\Project;

use App\Domain\Project\Exceptions\InvalidProjectStatusTransition;
use App\Domain\Skill\SkillId;
use DateTimeImmutable;
use InvalidArgumentException;

final class Project
{
    private ProjectId $id;

    private ProjectName $name;

    private ProjectStatus $status;

    private ?DateTimeImmutable $plannedStartDate = null;

    private ?DateTimeImmutable $plannedEndDate = null;

    /** @var array<string, RequiredSkill> SkillId文字列でキー */
    private array $requiredSkills = [];

    /** @var object[] */
    private array $domainEvents = [];

    public function __construct(ProjectId $id, ProjectName $name, ?ProjectStatus $status = null)
    {
        $this->id = $id;
        $this->name = $name;
        // 既存動作との互換性のためデフォルトは Active。
        // Planning で始めたいユースケースでは明示指定する。
        $this->status = $status ?? ProjectStatus::Active;
    }

    public function id(): ProjectId
    {
        return $this->id;
    }

    public function name(): ProjectName
    {
        return $this->name;
    }

    public function status(): ProjectStatus
    {
        return $this->status;
    }

    public function rename(ProjectName $name): void
    {
        $this->name = $name;
    }

    public function plannedStartDate(): ?DateTimeImmutable
    {
        return $this->plannedStartDate;
    }

    public function plannedEndDate(): ?DateTimeImmutable
    {
        return $this->plannedEndDate;
    }

    /**
     * プロジェクトの計画期間を設定する。
     * 両方 null ならクリア、片方のみ設定は不可。end <= start も不可。
     */
    public function setPlannedPeriod(?DateTimeImmutable $start, ?DateTimeImmutable $end): void
    {
        if ($start === null xor $end === null) {
            throw new InvalidArgumentException('planned period must be either both null or both set.');
        }
        if ($start !== null && $end !== null && $end <= $start) {
            throw new InvalidArgumentException('planned_end_date must be after planned_start_date.');
        }
        $this->plannedStartDate = $start;
        $this->plannedEndDate = $end;
    }

    /**
     * 指定月 (monthStart から翌月 monthStart までの半開区間) が計画期間と重なるか。
     * 期間未設定のプロジェクトは常に true (需要を全バケットに寄せる既存動作を保持)。
     */
    public function overlapsMonth(DateTimeImmutable $monthStart, DateTimeImmutable $monthEnd): bool
    {
        if ($this->plannedStartDate === null || $this->plannedEndDate === null) {
            return true;
        }

        return $this->plannedStartDate <= $monthEnd && $this->plannedEndDate >= $monthStart;
    }

    /**
     * ライフサイクル状態を遷移させる。
     *
     * 不正な遷移は {@see InvalidProjectStatusTransition} を投げる。
     * Active/Completed/Canceled へ遷移した際、対応するドメインイベントを発火する。
     */
    public function changeStatus(ProjectStatus $next): void
    {
        if ($this->status === $next) {
            return; // idempotent
        }
        if (! $this->status->canTransitionTo($next)) {
            throw InvalidProjectStatusTransition::from($this->status, $next);
        }
        $this->status = $next;

        $this->domainEvents[] = match ($next) {
            ProjectStatus::Active => new Events\ProjectActivated($this->id),
            ProjectStatus::Completed => new Events\ProjectCompleted($this->id),
            ProjectStatus::Canceled => new Events\ProjectCanceled($this->id),
            ProjectStatus::Planning => throw InvalidProjectStatusTransition::from($this->status, $next),
        };
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    /** @return RequiredSkill[] */
    public function requiredSkills(): array
    {
        return array_values($this->requiredSkills);
    }

    public function addOrUpdateRequirement(
        RequiredSkillId $requiredSkillId,
        SkillId $skillId,
        RequiredProficiency $minimumProficiency,
        int $headcount
    ): void {
        $key = $skillId->toString();
        $this->requiredSkills[$key] = new RequiredSkill(
            $requiredSkillId,
            $skillId,
            $minimumProficiency,
            $headcount
        );
        $this->domainEvents[] = new Events\ProjectRequirementChanged($this->id, $skillId);
    }

    public function requirementFor(SkillId $skillId): ?RequiredSkill
    {
        $key = $skillId->toString();

        return $this->requiredSkills[$key] ?? null;
    }

    /** @return object[] */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }
}
