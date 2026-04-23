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
    <section className="bg-white border border-gray-200 rounded-lg shadow-sm p-4">
      <div className="flex flex-wrap items-center justify-between gap-2 mb-3">
        <h2 className="text-sm font-semibold text-gray-800">{t('trend.title')}</h2>
        <div className="flex flex-wrap items-center gap-3">
          <select
            value={metric}
            onChange={(e) => setMetric(e.target.value as MetricKey)}
            className="px-2 py-1 text-xs border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            {METRICS.map((m) => (
              <option key={m.key} value={m.key}>
                {t(m.labelKey)}
              </option>
            ))}
          </select>
          <div className="flex items-center gap-1">
            {DAYS_PRESETS.map((d) => (
              <button
                key={d}
                onClick={() => setDays(d)}
                className={`px-2.5 py-1 text-xs rounded-full border transition-colors ${
                  days === d
                    ? 'bg-blue-100 border-blue-400 text-blue-700 font-medium'
                    : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-50'
                }`}
              >
                {d}{t('trend.daysSuffix')}
              </button>
            ))}
          </div>
        </div>
      </div>

      {query.isLoading && <div className="h-56 animate-pulse bg-gray-100 rounded" />}

      {query.isError && (
        <div className="p-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">
          {t('trend.loadFailed')}
        </div>
      )}

      {query.data && chartData.length === 0 && (
        <div className="py-10 text-center text-sm text-gray-500">
          {t('trend.empty')}<code className="px-1.5 py-0.5 bg-gray-100 rounded text-xs mx-1">php artisan kpi:snapshot-capture</code>{t('trend.emptyHint')}
        </div>
      )}

      {query.data && chartData.length > 0 && (
        <div className="h-56">
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={chartData} margin={{ top: 10, right: 20, bottom: 0, left: 0 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
              <XAxis dataKey="date" tick={{ fontSize: 11 }} />
              <YAxis tick={{ fontSize: 11 }} />
              <Tooltip
                formatter={(value: number) => [`${value}${meta.unit}`, metaLabel]}
                labelFormatter={(label: string) => t('trend.dateLabel', { date: label })}
                contentStyle={{ fontSize: 12 }}
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
