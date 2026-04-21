<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Allocation\Events\AllocationCreated;
use App\Domain\Allocation\Events\AllocationRevoked;
use App\Domain\Availability\Events\AbsenceCanceled;
use App\Domain\Availability\Events\AbsenceRegistered;
use App\Domain\Member\Events\MemberCreated;
use App\Domain\Member\Events\MemberSkillUpdated;
use App\Domain\Project\Events\ProjectActivated;
use App\Domain\Project\Events\ProjectCanceled;
use App\Domain\Project\Events\ProjectCompleted;
use App\Domain\Project\Events\ProjectRequirementChanged;
use App\Listeners\CreateNotification;
use App\Listeners\RecordAuditLog;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // すべてのドメインイベントを監査ログに記録
        foreach ([
            AllocationCreated::class,
            AllocationRevoked::class,
            MemberCreated::class,
            MemberSkillUpdated::class,
            ProjectRequirementChanged::class,
            ProjectActivated::class,
            ProjectCompleted::class,
            ProjectCanceled::class,
            AbsenceRegistered::class,
            AbsenceCanceled::class,
        ] as $eventClass) {
            Event::listen($eventClass, [RecordAuditLog::class, 'handle']);
            Event::listen($eventClass, [CreateNotification::class, 'handle']);
        }
    }
}
