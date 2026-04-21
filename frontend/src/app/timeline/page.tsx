'use client';

import { useState } from 'react';
import { AppHeader } from '@/components/layout/AppHeader';
import { GanttChart } from '@/components/molecules/GanttChart';
import { useTimeline } from '@/features/timeline/api';

const PRESETS = [
  { label: 'Next 3 months', months: 3 },
  { label: 'Next 6 months', months: 6 },
  { label: 'Next 12 months', months: 12 },
];

function todayIso(): string {
  return new Date().toISOString().slice(0, 10);
}

function addMonths(iso: string, n: number): string {
  const d = new Date(iso);
  d.setMonth(d.getMonth() + n);
  return d.toISOString().slice(0, 10);
}

export default function TimelinePage() {
  const [periodStart, setPeriodStart] = useState(todayIso());
  const [periodEnd, setPeriodEnd] = useState(addMonths(todayIso(), 6));
  const timeline = useTimeline(periodStart, periodEnd);

  const applyPreset = (months: number) => {
    const start = todayIso();
    setPeriodStart(start);
    setPeriodEnd(addMonths(start, months));
  };

  // Legend (unique projects)
  const projectLegend = timeline.data
    ? Array.from(
        new Map(
          timeline.data.rows
            .flatMap((r) => r.allocations)
            .map((a) => [a.projectId, a.projectName] as const),
        ).entries(),
      )
    : [];

  return (
    <>
      <AppHeader />
      <div className="max-w-[1400px] mx-auto px-4 py-8 space-y-4">
        <div className="flex items-baseline justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Timeline</h1>
            <p className="text-sm text-gray-500 mt-1">
              Horizontal Gantt view of active resource allocations.
            </p>
          </div>
        </div>

        <div className="flex items-end flex-wrap gap-3 p-4 bg-white rounded-lg border border-gray-200">
          <div>
            <label className="block text-xs font-medium text-gray-700 mb-1">From</label>
            <input
              type="date"
              value={periodStart}
              onChange={(e) => setPeriodStart(e.target.value)}
              className="px-3 py-1.5 text-sm border border-gray-300 rounded-md"
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-700 mb-1">To</label>
            <input
              type="date"
              value={periodEnd}
              onChange={(e) => setPeriodEnd(e.target.value)}
              className="px-3 py-1.5 text-sm border border-gray-300 rounded-md"
            />
          </div>
          <div className="flex gap-1.5 ml-auto">
            {PRESETS.map((p) => (
              <button
                key={p.months}
                onClick={() => applyPreset(p.months)}
                className="px-3 py-1.5 text-xs font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-100 rounded-md border border-gray-300"
              >
                {p.label}
              </button>
            ))}
          </div>
        </div>

        {projectLegend.length > 0 && (
          <div className="flex flex-wrap items-center gap-3 text-xs text-gray-600 px-2">
            <span className="font-semibold">Projects:</span>
            {projectLegend.map(([id, name]) => (
              <GanttLegendSwatch key={id} projectId={id} projectName={name} />
            ))}
          </div>
        )}

        {timeline.isLoading && (
          <div className="p-12 text-center text-gray-500">Loading timeline…</div>
        )}
        {timeline.error && (
          <div className="p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
            Failed to load timeline: {(timeline.error as Error).message}
          </div>
        )}
        {timeline.data && <GanttChart data={timeline.data} />}
      </div>
    </>
  );
}

const COLORS = [
  '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
  '#ec4899', '#14b8a6', '#f97316', '#84cc16', '#06b6d4',
  '#a855f7', '#eab308',
];

function colorFor(projectId: string): string {
  let hash = 0;
  for (let i = 0; i < projectId.length; i++) {
    hash = (hash * 31 + projectId.charCodeAt(i)) | 0;
  }
  return COLORS[Math.abs(hash) % COLORS.length] ?? COLORS[0]!;
}

function GanttLegendSwatch({ projectId, projectName }: { projectId: string; projectName: string }) {
  return (
    <span className="inline-flex items-center gap-1.5">
      <span
        className="inline-block w-3 h-3 rounded"
        style={{ backgroundColor: colorFor(projectId) }}
      />
      {projectName}
    </span>
  );
}
