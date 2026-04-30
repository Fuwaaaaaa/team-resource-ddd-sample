<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Allocation\Events\AllocationCreated;
use App\Domain\Allocation\Events\AllocationRevoked;
use App\Domain\AllocationChangeRequest\Events\AllocationChangeRequestApproved;
use App\Domain\AllocationChangeRequest\Events\AllocationChangeRequestRejected;
use App\Domain\AllocationChangeRequest\Events\AllocationChangeRequestSubmitted;
use App\Domain\Authorization\Events\UserCreated;
use App\Domain\Authorization\Events\UserDisabled;
use App\Domain\Authorization\Events\UserEnabled;
use App\Domain\Authorization\Events\UserPasswordReset;
use App\Domain\Authorization\Events\UserRoleChanged;
use App\Domain\Availability\Events\AbsenceCanceled;
use App\Domain\Availability\Events\AbsenceRegistered;
use App\Domain\Member\Events\MemberCreated;
use App\Domain\Member\Events\MemberSkillUpdated;
use App\Domain\Project\Events\ProjectActivated;
use App\Domain\Project\Events\ProjectCanceled;
use App\Domain\Project\Events\ProjectCompleted;
use App\Domain\Project\Events\ProjectRequirementChanged;
use App\Listeners\CreateNotification;
use App\Listeners\PersistDomainEvent;
use App\Listeners\RecordAuditLog;
use App\Listeners\SendEmailNotification;
use App\Listeners\SendSlackNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Seeder で利用する UUID v5 / namespace ヘルパ
        if (! Str::hasMacro('uuid5')) {
            Str::macro('uuid5', fn (string $namespace, string $name) => Uuid::uuid5($namespace, $name));
        }
        if (! Str::hasMacro('uuid5Namespace')) {
            Str::macro('uuid5Namespace', fn (string $name) => match (strtolower($name)) {
                'dns' => Uuid::NAMESPACE_DNS,
                'url' => Uuid::NAMESPACE_URL,
                'oid' => Uuid::NAMESPACE_OID,
                'x500' => Uuid::NAMESPACE_X500,
                default => throw new \InvalidArgumentException("Unknown UUID namespace: {$name}"),
            });
        }
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
            AllocationChangeRequestSubmitted::class,
            AllocationChangeRequestApproved::class,
            AllocationChangeRequestRejected::class,
            // Authorization (User authentication identity, not a domain aggregate).
            // Email/Slack/in-app notification listeners skip these via NotificationContentBuilder
            // returning null — only PersistDomainEvent and RecordAuditLog react.
            UserCreated::class,
            UserRoleChanged::class,
            UserPasswordReset::class,
            UserDisabled::class,
            UserEnabled::class,
        ] as $eventClass) {
            Event::listen($eventClass, [PersistDomainEvent::class, 'handle']);
            Event::listen($eventClass, [RecordAuditLog::class, 'handle']);
            Event::listen($eventClass, [CreateNotification::class, 'handle']);
            Event::listen($eventClass, [SendEmailNotification::class, 'handle']);
            Event::listen($eventClass, [SendSlackNotification::class, 'handle']);
        }
    }
}
