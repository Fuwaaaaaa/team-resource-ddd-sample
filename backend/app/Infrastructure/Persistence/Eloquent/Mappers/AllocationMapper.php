<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Mappers;

use App\Domain\Allocation\AllocationId;
use App\Domain\Allocation\AllocationPercentage;
use App\Domain\Allocation\AllocationPeriod;
use App\Domain\Allocation\AllocationStatus;
use App\Domain\Allocation\ResourceAllocation;
use App\Domain\Member\MemberId;
use App\Domain\Project\ProjectId;
use App\Domain\Skill\SkillId;
use App\Infrastructure\Persistence\Eloquent\Models\AllocationModel;
use DateTimeImmutable;
use ReflectionClass;

/**
 * ResourceAllocation はコンストラクタで AllocationCreated イベントを発火するため、
 * DB からの再構成にはリフレクションを使ってコンストラクタをバイパスする。
 */
final class AllocationMapper
{
    public static function toDomain(AllocationModel $model): ResourceAllocation
    {
        $ref = new ReflectionClass(ResourceAllocation::class);
        /** @var ResourceAllocation $allocation */
        $allocation = $ref->newInstanceWithoutConstructor();

        $periodStart = $model->period_start instanceof \DateTimeInterface
            ? DateTimeImmutable::createFromInterface($model->period_start)
            : new DateTimeImmutable((string) $model->period_start);
        $periodEnd = $model->period_end instanceof \DateTimeInterface
            ? DateTimeImmutable::createFromInterface($model->period_end)
            : new DateTimeImmutable((string) $model->period_end);

        $props = [
            'id' => new AllocationId((string) $model->id),
            'memberId' => new MemberId((string) $model->member_id),
            'projectId' => new ProjectId((string) $model->project_id),
            'skillId' => new SkillId((string) $model->skill_id),
            'percentage' => new AllocationPercentage((int) $model->allocation_percentage),
            'period' => new AllocationPeriod($periodStart, $periodEnd),
            'status' => AllocationStatus::fromString((string) $model->status),
            'domainEvents' => [],
        ];

        foreach ($props as $name => $value) {
            $prop = $ref->getProperty($name);
            $prop->setValue($allocation, $value);
        }

        return $allocation;
    }

    /** @return array<string, mixed> */
    public static function toRow(ResourceAllocation $allocation): array
    {
        return [
            'id' => $allocation->id()->toString(),
            'member_id' => $allocation->memberId()->toString(),
            'project_id' => $allocation->projectId()->toString(),
            'skill_id' => $allocation->skillId()->toString(),
            'allocation_percentage' => $allocation->percentage()->value(),
            'period_start' => $allocation->period()->startDate()->format('Y-m-d'),
            'period_end' => $allocation->period()->endDate()->format('Y-m-d'),
            'status' => $allocation->status()->toString(),
        ];
    }
}
