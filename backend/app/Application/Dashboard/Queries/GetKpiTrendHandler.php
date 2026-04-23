<?php

declare(strict_types=1);

namespace App\Application\Dashboard\Queries;

use App\Application\Dashboard\DTOs\KpiTrendDto;
use App\Application\Dashboard\DTOs\KpiTrendPointDto;
use App\Infrastructure\Persistence\Eloquent\Models\KpiSnapshotModel;
use DateInterval;
use DateTimeImmutable;

/**
 * 指定日から $days 日遡った範囲の KPI スナップショットを日付昇順で返す。
 * 欠損日は配列に含めず、連続性の補完はフロントエンド側で行う。
 */
final class GetKpiTrendHandler
{
    public function handle(GetKpiTrendQuery $query): KpiTrendDto
    {
        $end = new DateTimeImmutable($query->referenceDate);
        $start = $end->sub(new DateInterval(sprintf('P%dD', $query->days - 1)));

        $rows = KpiSnapshotModel::query()
            ->whereDate('snapshot_date', '>=', $start->format('Y-m-d'))
            ->whereDate('snapshot_date', '<=', $end->format('Y-m-d'))
            ->orderBy('snapshot_date')
            ->get();

        $points = [];
        foreach ($rows as $row) {
            $points[] = new KpiTrendPointDto(
                date: $row->snapshot_date->format('Y-m-d'),
                averageFulfillmentRate: (float) $row->average_fulfillment_rate,
                activeProjectCount: (int) $row->active_project_count,
                overloadedMemberCount: (int) $row->overloaded_member_count,
                upcomingEndsThisWeek: (int) $row->upcoming_ends_this_week,
                skillGapsTotal: (int) $row->skill_gaps_total,
            );
        }

        return new KpiTrendDto(
            referenceDate: $query->referenceDate,
            days: $query->days,
            points: $points,
        );
    }
}
