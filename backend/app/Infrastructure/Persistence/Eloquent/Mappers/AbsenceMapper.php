<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Mappers;

use App\Domain\Availability\Absence;
use App\Domain\Availability\AbsenceId;
use App\Domain\Availability\AbsencePeriod;
use App\Domain\Availability\AbsenceType;
use App\Domain\Member\MemberId;
use App\Infrastructure\Persistence\Eloquent\Models\AbsenceModel;
use DateTimeImmutable;
use ReflectionClass;

/**
 * Absence 集約と Eloquent モデル間のマッパー。
 *
 * DB からの再構成では、コンストラクタが発火する AbsenceRegistered イベントを
 * 再発火させないようにリフレクション経由で private プロパティをセットする。
 */
final class AbsenceMapper
{
    public static function toDomain(AbsenceModel $model): Absence
    {
        $ref = new ReflectionClass(Absence::class);
        /** @var Absence $absence */
        $absence = $ref->newInstanceWithoutConstructor();

        $startDate = new DateTimeImmutable($model->start_date->format('Y-m-d'));
        $endDate = new DateTimeImmutable($model->end_date->format('Y-m-d'));

        $props = [
            'id' => new AbsenceId((string) $model->id),
            'memberId' => new MemberId((string) $model->member_id),
            'period' => new AbsencePeriod($startDate, $endDate),
            'type' => AbsenceType::from((string) $model->type),
            'note' => (string) $model->note,
            'canceled' => (bool) $model->canceled,
            'domainEvents' => [],
        ];

        foreach ($props as $name => $value) {
            $prop = $ref->getProperty($name);
            $prop->setValue($absence, $value);
        }

        return $absence;
    }

    /** @return array<string, mixed> */
    public static function toRow(Absence $absence): array
    {
        return [
            'id' => $absence->id()->toString(),
            'member_id' => $absence->memberId()->toString(),
            'start_date' => $absence->period()->startDate()->format('Y-m-d'),
            'end_date' => $absence->period()->endDate()->format('Y-m-d'),
            'type' => $absence->type()->value,
            'note' => $absence->note(),
            'canceled' => $absence->isCanceled(),
        ];
    }
}
