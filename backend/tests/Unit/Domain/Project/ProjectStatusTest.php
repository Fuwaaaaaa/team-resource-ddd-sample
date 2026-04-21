<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Project;

use App\Domain\Project\Events\ProjectActivated;
use App\Domain\Project\Events\ProjectCanceled;
use App\Domain\Project\Events\ProjectCompleted;
use App\Domain\Project\Exceptions\InvalidProjectStatusTransition;
use App\Domain\Project\Project;
use App\Domain\Project\ProjectId;
use App\Domain\Project\ProjectName;
use App\Domain\Project\ProjectStatus;
use PHPUnit\Framework\TestCase;

final class ProjectStatusTest extends TestCase
{
    public function test_default_status_is_active_for_backward_compatibility(): void
    {
        $project = $this->makeProject();
        $this->assertSame(ProjectStatus::Active, $project->status());
    }

    public function test_can_start_in_planning_when_explicit(): void
    {
        $project = new Project(new ProjectId('p1'), new ProjectName('X'), ProjectStatus::Planning);
        $this->assertSame(ProjectStatus::Planning, $project->status());
    }

    public function test_planning_to_active_emits_activated(): void
    {
        $project = new Project(new ProjectId('p1'), new ProjectName('X'), ProjectStatus::Planning);
        $project->changeStatus(ProjectStatus::Active);

        $events = $project->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ProjectActivated::class, $events[0]);
    }

    public function test_active_to_completed_emits_completed(): void
    {
        $project = $this->makeProject();
        $project->changeStatus(ProjectStatus::Completed);

        $events = $project->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ProjectCompleted::class, $events[0]);
        $this->assertTrue($project->isTerminal());
    }

    public function test_active_to_canceled_emits_canceled(): void
    {
        $project = $this->makeProject();
        $project->changeStatus(ProjectStatus::Canceled);

        $events = $project->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ProjectCanceled::class, $events[0]);
    }

    public function test_same_status_is_idempotent(): void
    {
        $project = $this->makeProject();
        $project->changeStatus(ProjectStatus::Active);

        $this->assertEmpty($project->pullDomainEvents());
    }

    public function test_completed_is_terminal(): void
    {
        $project = $this->makeProject();
        $project->changeStatus(ProjectStatus::Completed);

        $this->expectException(InvalidProjectStatusTransition::class);
        $project->changeStatus(ProjectStatus::Active);
    }

    public function test_canceled_is_terminal(): void
    {
        $project = $this->makeProject();
        $project->changeStatus(ProjectStatus::Canceled);

        $this->expectException(InvalidProjectStatusTransition::class);
        $project->changeStatus(ProjectStatus::Completed);
    }

    public function test_planning_cannot_skip_to_completed(): void
    {
        $project = new Project(new ProjectId('p1'), new ProjectName('X'), ProjectStatus::Planning);

        $this->expectException(InvalidProjectStatusTransition::class);
        $project->changeStatus(ProjectStatus::Completed);
    }

    public function test_status_counts_for_capacity(): void
    {
        $this->assertTrue(ProjectStatus::Planning->countsForCapacity());
        $this->assertTrue(ProjectStatus::Active->countsForCapacity());
        $this->assertFalse(ProjectStatus::Completed->countsForCapacity());
        $this->assertFalse(ProjectStatus::Canceled->countsForCapacity());
    }

    private function makeProject(): Project
    {
        return new Project(new ProjectId('p1'), new ProjectName('Test'));
    }
}
