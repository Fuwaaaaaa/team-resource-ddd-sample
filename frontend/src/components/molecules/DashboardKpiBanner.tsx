'use client';

import Link from 'next/link';
import { useKpiSummary } from '@/features/dashboard/api';

function fulfillmentTone(rate: number): 'good' | 'warn' | 'bad' {
  if (rate >= 90) return 'good';
  if (rate >= 60) return 'warn';
  return 'bad';
}

function KpiCard({
  label,
  value,
  sub,
  tone,
  href,
}: {
  label: string;
  value: string;
  sub?: string;
  tone: 'default' | 'good' | 'warn' | 'bad';
  href?: string;
}) {
  const toneClasses =
    tone === 'good'
      ? 'text-green-700'
      : tone === 'warn'
        ? 'text-amber-700'
        : tone === 'bad'
          ? 'text-red-700'
          : 'text-gray-900';
  const body = (
    <div className="flex-1 min-w-[180px] bg-white border border-gray-200 rounded-lg shadow-sm px-4 py-3 relative hover:border-indigo-300 transition">
      <div className="text-xs text-gray-500">{label}</div>
      <div className={`text-2xl font-bold mt-1 ${toneClasses}`}>{value}</div>
      {sub && <div className="text-xs text-gray-500 mt-0.5">{sub}</div>}
      {href && (
        <span className="absolute top-2 right-2 text-[11px] text-indigo-600 opacity-70">
          詳細 →
        </span>
      )}
    </div>
  );
  return href ? (
    <Link href={href} className="flex-1 min-w-[180px] no-underline">
      {body}
    </Link>
  ) : (
    body
  );
}

export function DashboardKpiBanner({ referenceDate }: { referenceDate: string }) {
  const kpi = useKpiSummary(referenceDate);

  if (kpi.isLoading) {
    return (
      <div className="flex flex-wrap gap-3">
        {[0, 1, 2, 3].map((i) => (
          <div
            key={i}
            className="flex-1 min-w-[180px] bg-white border border-gray-200 rounded-lg shadow-sm px-4 py-3 animate-pulse"
          >
            <div className="h-3 w-20 bg-gray-200 rounded" />
            <div className="h-6 w-16 bg-gray-200 rounded mt-2" />
            <div className="h-3 w-24 bg-gray-200 rounded mt-2" />
          </div>
        ))}
      </div>
    );
  }

  if (kpi.isError || !kpi.data) {
    return (
      <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
        KPI サマリの取得に失敗しました。
      </div>
    );
  }

  const d = kpi.data;
  const fulfillmentTon = fulfillmentTone(d.averageFulfillmentRate);

  return (
    <div className="flex flex-wrap gap-3">
      <KpiCard
        label="全 active/planning の平均充足率"
        value={`${d.averageFulfillmentRate.toFixed(1)}%`}
        sub={`${d.activeProjectCount} プロジェクト`}
        tone={d.activeProjectCount === 0 ? 'default' : fulfillmentTon}
        href="/projects/compare"
      />
      <KpiCard
        label="過負荷メンバー数"
        value={String(d.overloadedMemberCount)}
        sub={d.overloadedMemberCount > 0 ? '要対処' : '余裕あり'}
        tone={d.overloadedMemberCount > 0 ? 'bad' : 'default'}
        href="/members"
      />
      <KpiCard
        label="今週終了するアサイン"
        value={String(d.upcomingEndsThisWeek)}
        sub="7 日以内"
        tone={d.upcomingEndsThisWeek > 0 ? 'warn' : 'default'}
      />
      <KpiCard
        label="スキル不足人数(総計)"
        value={String(d.skillGapsTotal)}
        sub="全 active/planning の gap 合計"
        tone={d.skillGapsTotal > 0 ? 'warn' : 'default'}
      />
    </div>
  );
}
