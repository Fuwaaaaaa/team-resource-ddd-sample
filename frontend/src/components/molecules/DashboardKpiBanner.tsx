'use client';

import Link from 'next/link';
import { useKpiSummary } from '@/features/dashboard/api';
import { useTranslation } from '@/lib/i18n/useTranslation';

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
      ? 'text-green-700 dark:text-green-400'
      : tone === 'warn'
        ? 'text-amber-700 dark:text-amber-400'
        : tone === 'bad'
          ? 'text-red-700 dark:text-red-400'
          : 'text-fg';
  const body = (
    <div className="flex-1 min-w-[180px] bg-surface border border-border rounded-lg shadow-sm px-4 py-3 relative hover:border-primary transition">
      <div className="text-xs text-fg-muted">{label}</div>
      <div className={`text-2xl font-bold mt-1 ${toneClasses}`}>{value}</div>
      {sub && <div className="text-xs text-fg-muted mt-0.5">{sub}</div>}
      {href && (
        <span className="absolute top-2 right-2 text-[11px] text-primary opacity-70">
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
  const t = useTranslation();

  if (kpi.isLoading) {
    return (
      <div className="flex flex-wrap gap-3">
        {[0, 1, 2, 3].map((i) => (
          <div
            key={i}
            className="flex-1 min-w-[180px] bg-surface border border-border rounded-lg shadow-sm px-4 py-3 animate-pulse"
          >
            <div className="h-3 w-20 bg-surface-muted rounded" />
            <div className="h-6 w-16 bg-surface-muted rounded mt-2" />
            <div className="h-3 w-24 bg-surface-muted rounded mt-2" />
          </div>
        ))}
      </div>
    );
  }

  if (kpi.isError || !kpi.data) {
    return (
      <div className="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-sm text-red-700 dark:text-red-300">
        {t('kpi.loadFailed')}
      </div>
    );
  }

  const d = kpi.data;
  const fulfillmentTon = fulfillmentTone(d.averageFulfillmentRate);

  return (
    <div className="flex flex-wrap gap-3">
      <KpiCard
        label={t('kpi.fulfillmentRate')}
        value={`${d.averageFulfillmentRate.toFixed(1)}%`}
        sub={t('kpi.projectsCount', { count: d.activeProjectCount })}
        tone={d.activeProjectCount === 0 ? 'default' : fulfillmentTon}
        href="/projects/compare"
      />
      <KpiCard
        label={t('kpi.overloadedMembers')}
        value={String(d.overloadedMemberCount)}
        sub={d.overloadedMemberCount > 0 ? t('kpi.needsAttention') : t('kpi.capacityFine')}
        tone={d.overloadedMemberCount > 0 ? 'bad' : 'default'}
        href="/members"
      />
      <KpiCard
        label={t('kpi.upcomingEnds')}
        value={String(d.upcomingEndsThisWeek)}
        sub={t('kpi.within7Days')}
        tone={d.upcomingEndsThisWeek > 0 ? 'warn' : 'default'}
      />
      <KpiCard
        label={t('kpi.skillGaps')}
        value={String(d.skillGapsTotal)}
        sub={t('kpi.skillGapsSub')}
        tone={d.skillGapsTotal > 0 ? 'warn' : 'default'}
      />
    </div>
  );
}
