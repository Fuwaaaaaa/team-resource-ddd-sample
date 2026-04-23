'use client';

import { useMemo, useState } from 'react';
import { useCapacityForecast } from '@/features/dashboard/api';
import type { ForecastSeverity, SkillForecastDto } from '@/features/dashboard/types';
import { useTranslation } from '@/lib/i18n/useTranslation';

const MONTHS_PRESETS = [3, 6, 12] as const;
type MonthsPreset = typeof MONTHS_PRESETS[number];

function severityClasses(sev: ForecastSeverity): string {
  switch (sev) {
    case 'ok':
      return 'bg-green-50 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-800';
    case 'watch':
      return 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800';
    case 'critical':
      return 'bg-red-50 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-800';
  }
}

function formatGap(gap: number): string {
  const sign = gap > 0 ? '+' : '';
  return `${sign}${gap.toFixed(1)}`;
}

export function CapacityForecastWidget({ referenceDate }: { referenceDate: string }) {
  const [months, setMonths] = useState<MonthsPreset>(6);
  const query = useCapacityForecast(referenceDate, months);
  const t = useTranslation();

  // バケット全体でユニークなスキル一覧 (表の行) を導出 — 表示順は name 昇順で固定
  const skillRows = useMemo(() => {
    if (!query.data) return [] as Array<{ skillId: string; skillName: string }>;
    const seen = new Map<string, string>();
    for (const b of query.data.buckets) {
      for (const s of b.skills) {
        if (!seen.has(s.skillId)) seen.set(s.skillId, s.skillName);
      }
    }
    return Array.from(seen, ([skillId, skillName]) => ({ skillId, skillName })).sort((a, b) =>
      a.skillName.localeCompare(b.skillName),
    );
  }, [query.data]);

  // (skillId, month) → SkillForecastDto ルックアップ
  const cellLookup = useMemo(() => {
    const map = new Map<string, SkillForecastDto>();
    if (!query.data) return map;
    for (const b of query.data.buckets) {
      for (const s of b.skills) {
        map.set(`${s.skillId}:${b.month}`, s);
      }
    }
    return map;
  }, [query.data]);

  return (
    <section className="bg-surface border border-border rounded-lg shadow-sm p-4">
      <div className="flex items-center justify-between mb-3 gap-2 flex-wrap">
        <h2 className="text-sm font-semibold text-fg">{t('forecast.title')}</h2>
        <div className="flex items-center gap-2 flex-wrap">
          <span className="text-xs text-fg-muted">{t('forecast.periodLabel')}</span>
          {MONTHS_PRESETS.map((m) => (
            <button
              key={m}
              onClick={() => setMonths(m)}
              className={`px-2.5 py-1 text-xs rounded-full border transition-colors ${
                months === m
                  ? 'bg-primary/10 border-primary text-primary font-medium'
                  : 'bg-surface border-border text-fg-muted hover:bg-surface-muted'
              }`}
            >
              {m}{t('forecast.monthsSuffix')}
            </button>
          ))}
        </div>
      </div>

      {query.isLoading && (
        <div className="h-32 animate-pulse bg-surface-muted rounded" />
      )}

      {query.isError && (
        <div className="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded text-sm text-red-700 dark:text-red-300">
          {t('forecast.loadFailed')}
        </div>
      )}

      {query.data && skillRows.length === 0 && (
        <div className="py-6 text-center text-sm text-fg-muted">
          {t('forecast.noDemand')}
        </div>
      )}

      {query.data && skillRows.length > 0 && (
        <div className="overflow-x-auto">
          <table className="min-w-full text-xs">
            <thead>
              <tr className="border-b border-border">
                <th className="text-left py-2 px-2 font-medium text-fg-muted sticky left-0 bg-surface">
                  {t('forecast.skillColumn')}
                </th>
                {query.data.buckets.map((b) => (
                  <th key={b.month} className="text-center py-2 px-2 font-medium text-fg-muted whitespace-nowrap">
                    {b.month}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {skillRows.map((row) => (
                <tr key={row.skillId} className="border-b border-border last:border-b-0">
                  <td className="py-2 px-2 font-medium text-fg sticky left-0 bg-surface whitespace-nowrap">
                    {row.skillName}
                  </td>
                  {query.data!.buckets.map((b) => {
                    const cell = cellLookup.get(`${row.skillId}:${b.month}`);
                    if (!cell) {
                      return (
                        <td key={b.month} className="py-2 px-2 text-center text-fg-muted/40">
                          —
                        </td>
                      );
                    }
                    return (
                      <td key={b.month} className="py-1 px-1">
                        <div
                          title={`需要 ${cell.demandHeadcount} / 供給 ${cell.supplyHeadcountEquivalent.toFixed(1)}`}
                          className={`border rounded px-2 py-1 text-center ${severityClasses(cell.severity)}`}
                        >
                          <div className="font-semibold">{formatGap(cell.gap)}</div>
                          <div className="text-[10px] opacity-80">
                            {cell.supplyHeadcountEquivalent.toFixed(1)}/{cell.demandHeadcount}
                          </div>
                        </div>
                      </td>
                    );
                  })}
                </tr>
              ))}
            </tbody>
          </table>
          <p className="mt-2 text-[11px] text-fg-muted">{t('forecast.cellHint')}</p>
        </div>
      )}
    </section>
  );
}
