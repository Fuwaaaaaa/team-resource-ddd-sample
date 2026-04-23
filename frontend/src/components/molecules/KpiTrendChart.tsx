'use client';

import { useMemo, useState } from 'react';
import {
  CartesianGrid,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import { useKpiTrend } from '@/features/dashboard/api';
import type { KpiTrendPointDto } from '@/features/dashboard/types';
import { useTranslation } from '@/lib/i18n/useTranslation';
import type { TranslationKey } from '@/lib/i18n/messages';

const DAYS_PRESETS = [7, 30, 90] as const;
type DaysPreset = typeof DAYS_PRESETS[number];

type MetricKey =
  | 'averageFulfillmentRate'
  | 'overloadedMemberCount'
  | 'skillGapsTotal'
  | 'upcomingEndsThisWeek';

const METRICS: { key: MetricKey; labelKey: TranslationKey; unit: string; stroke: string }[] = [
  { key: 'averageFulfillmentRate', labelKey: 'trend.metricFulfillment', unit: '%', stroke: '#4f46e5' },
  { key: 'overloadedMemberCount', labelKey: 'trend.metricOverloaded', unit: '', stroke: '#dc2626' },
  { key: 'skillGapsTotal', labelKey: 'trend.metricSkillGaps', unit: '', stroke: '#ca8a04' },
  { key: 'upcomingEndsThisWeek', labelKey: 'trend.metricUpcomingEnds', unit: '', stroke: '#0891b2' },
];

export function KpiTrendChart({ referenceDate }: { referenceDate: string }) {
  const [days, setDays] = useState<DaysPreset>(30);
  const [metric, setMetric] = useState<MetricKey>('averageFulfillmentRate');
  const query = useKpiTrend(referenceDate, days);
  const t = useTranslation();

  const meta = useMemo(() => METRICS.find((m) => m.key === metric)!, [metric]);
  const metaLabel = t(meta.labelKey);

  const chartData = useMemo(() => {
    if (!query.data) return [] as Array<{ date: string; value: number }>;
    return query.data.points.map((p: KpiTrendPointDto) => ({
      date: p.date.slice(5), // MM-DD (省略形)
      value: p[metric],
    }));
  }, [query.data, metric]);

  return (
    <section className="bg-surface border border-border rounded-lg shadow-sm p-4">
      <div className="flex flex-wrap items-center justify-between gap-2 mb-3">
        <h2 className="text-sm font-semibold text-fg">{t('trend.title')}</h2>
        <div className="flex flex-wrap items-center gap-3">
          <select
            value={metric}
            onChange={(e) => setMetric(e.target.value as MetricKey)}
            className="px-2 py-1 text-xs border border-border rounded-md bg-surface text-fg focus:outline-none focus:ring-2 focus:ring-primary"
          >
            {METRICS.map((m) => (
              <option key={m.key} value={m.key}>
                {t(m.labelKey)}
              </option>
            ))}
          </select>
          <div className="flex items-center gap-1 flex-wrap">
            {DAYS_PRESETS.map((d) => (
              <button
                key={d}
                onClick={() => setDays(d)}
                className={`px-2.5 py-1 text-xs rounded-full border transition-colors ${
                  days === d
                    ? 'bg-primary/10 border-primary text-primary font-medium'
                    : 'bg-surface border-border text-fg-muted hover:bg-surface-muted'
                }`}
              >
                {d}{t('trend.daysSuffix')}
              </button>
            ))}
          </div>
        </div>
      </div>

      {query.isLoading && <div className="h-56 animate-pulse bg-surface-muted rounded" />}

      {query.isError && (
        <div className="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded text-sm text-red-700 dark:text-red-300">
          {t('trend.loadFailed')}
        </div>
      )}

      {query.data && chartData.length === 0 && (
        <div className="py-10 text-center text-sm text-fg-muted">
          {t('trend.empty')}<code className="px-1.5 py-0.5 bg-surface-muted rounded text-xs mx-1">php artisan kpi:snapshot-capture</code>{t('trend.emptyHint')}
        </div>
      )}

      {query.data && chartData.length > 0 && (
        <div className="h-56">
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={chartData} margin={{ top: 10, right: 20, bottom: 0, left: 0 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="rgb(var(--color-border))" />
              <XAxis dataKey="date" tick={{ fontSize: 11, fill: 'rgb(var(--color-fg-muted))' }} stroke="rgb(var(--color-border))" />
              <YAxis tick={{ fontSize: 11, fill: 'rgb(var(--color-fg-muted))' }} stroke="rgb(var(--color-border))" />
              <Tooltip
                formatter={(value: number) => [`${value}${meta.unit}`, metaLabel]}
                labelFormatter={(label: string) => t('trend.dateLabel', { date: label })}
                contentStyle={{
                  fontSize: 12,
                  backgroundColor: 'rgb(var(--color-surface))',
                  border: '1px solid rgb(var(--color-border))',
                  color: 'rgb(var(--color-fg))',
                }}
              />
              <Line
                type="monotone"
                dataKey="value"
                name={metaLabel}
                stroke={meta.stroke}
                strokeWidth={2}
                dot={{ r: 3 }}
                activeDot={{ r: 5 }}
              />
            </LineChart>
          </ResponsiveContainer>
        </div>
      )}
    </section>
  );
}
