<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Dashboard\Queries\GetKpiSummaryHandler;
use App\Application\Dashboard\Queries\GetKpiSummaryQuery;
use App\Infrastructure\Persistence\Eloquent\Models\KpiSnapshotModel;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * 指定日 (デフォルト: 今日) の KPI サマリを計算して kpi_snapshots に upsert する。
 * 毎日定期実行することで時系列トレンドチャートのデータ源となる。
 */
class CaptureKpiSnapshotCommand extends Command
{
    protected $signature = 'kpi:snapshot-capture {--date= : 対象日 (Y-m-d, 省略時は today)}';

    protected $description = '指定日の KPI サマリをスナップショットとして保存';

    public function handle(GetKpiSummaryHandler $handler): int
    {
        $date = $this->option('date') ?: now()->format('Y-m-d');

        $dto = $handler->handle(new GetKpiSummaryQuery($date));

        $existing = KpiSnapshotModel::query()->whereDate('snapshot_date', $date)->first();
        $attributes = [
            'snapshot_date' => $date,
            'average_fulfillment_rate' => $dto->averageFulfillmentRate,
            'active_project_count' => $dto->activeProjectCount,
            'overloaded_member_count' => $dto->overloadedMemberCount,
            'upcoming_ends_this_week' => $dto->upcomingEndsThisWeek,
            'skill_gaps_total' => $dto->skillGapsTotal,
        ];
        if ($existing === null) {
            KpiSnapshotModel::create(['id' => (string) Str::uuid7(), ...$attributes]);
        } else {
            $existing->fill($attributes)->save();
        }

        $this->info("KPI snapshot captured for {$date}.");

        return self::SUCCESS;
    }
}
