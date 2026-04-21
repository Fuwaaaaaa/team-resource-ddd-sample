<?php

declare(strict_types=1);

namespace App\Application\Project\Commands;

use App\Application\Project\DTOs\ProjectDto;
use App\Domain\Allocation\ResourceAllocationRepositoryInterface;
use App\Domain\Project\ProjectId;
use App\Domain\Project\ProjectRepositoryInterface;
use App\Domain\Project\ProjectStatus;
use App\Infrastructure\Events\DomainEventDispatcher;
use InvalidArgumentException;

/**
 * プロジェクトのライフサイクル状態を遷移させる。
 *
 * Completed / Canceled に遷移したとき、当該プロジェクトのアクティブな
 * アロケーションを自動的に revoke する（ユースケース層の責務）。
 * これによりキャパシティ計算や overload 検知から即座に外れる。
 */
final class ChangeProjectStatusHandler
{
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
        private ResourceAllocationRepositoryInterface $allocationRepository,
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    public function handle(ChangeProjectStatusCommand $command): ProjectDto
    {
        $projectId = new ProjectId($command->projectId);
        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            throw new InvalidArgumentException("Project not found: {$command->projectId}");
        }

        $next = ProjectStatus::tryFrom($command->status)
            ?? throw new InvalidArgumentException("Invalid project status: {$command->status}");

        $project->changeStatus($next);
        $this->projectRepository->save($project);

        $events = $project->pullDomainEvents();

        // 終了状態への遷移時、そのプロジェクトのアクティブなアロケーションを全て revoke。
        if ($next->isTerminal()) {
            $allocations = $this->allocationRepository->findByProjectId($projectId);
            foreach ($allocations as $allocation) {
                if ($allocation->isActive()) {
                    $allocation->revoke();
                    $this->allocationRepository->save($allocation);
                    $events = array_merge($events, $allocation->pullDomainEvents());
                }
            }
        }

        $this->eventDispatcher->dispatchAll($events);

        return ProjectDto::fromDomain($project);
    }
}
