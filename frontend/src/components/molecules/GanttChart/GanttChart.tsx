'use client';

import { useMemo } from 'react';
import type { TimelineResponse } from '@/features/timeline/types';

export interface GanttChartProps {
  data: TimelineResponse;
}

// 12 色を回して project ID からカラーを決定（固定ハッシュ）
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

function daysBetween(a: Date, b: Date): number {
  return Math.round((b.getTime() - a.getTime()) / (1000 * 60 * 60 * 24));
}

function* monthTicks(start: Date, end: Date): Generator<Date> {
  const d = new Date(start.getFullYear(), start.getMonth(), 1);
  while (d <= end) {
    yield new Date(d);
    d.setMonth(d.getMonth() + 1);
  }
}

export function GanttChart({ data }: GanttChartProps) {
  const windowStart = useMemo(() => new Date(data.periodStart), [data.periodStart]);
  const windowEnd = useMemo(() => new Date(data.periodEnd), [data.periodEnd]);
  const totalDays = Math.max(1, daysBetween(windowStart, windowEnd));

  const ROW_HEIGHT = 40;
  const BAR_HEIGHT = 22;
  const HEADER_HEIGHT = 40;
  const LABEL_WIDTH = 180;
  const MIN_CHART_WIDTH = 800;
  const chartWidth = Math.max(MIN_CHART_WIDTH, totalDays * 4);
  const totalWidth = LABEL_WIDTH + chartWidth;
  const totalHeight = HEADER_HEIGHT + data.rows.length * ROW_HEIGHT + 20;

  const pxPerDay = chartWidth / totalDays;
  const xForDate = (iso: string): number => {
    const d = new Date(iso);
    const days = Math.max(0, Math.min(totalDays, daysBetween(windowStart, d)));
    return LABEL_WIDTH + days * pxPerDay;
  };

  if (data.rows.length === 0) {
    return (
      <div className="p-12 text-center text-gray-500">
        No active allocations in the selected window.
      </div>
    );
  }

  return (
    <div className="overflow-x-auto bg-white rounded-lg border border-gray-200">
      <svg
        width={totalWidth}
        height={totalHeight}
        role="img"
        aria-label="Resource allocation timeline"
        className="block"
      >
        {/* Month ticks */}
        {[...monthTicks(windowStart, windowEnd)].map((m) => {
          const x = xForDate(m.toISOString().slice(0, 10));
          if (x < LABEL_WIDTH) return null;
          const label = `${m.getFullYear()}-${String(m.getMonth() + 1).padStart(2, '0')}`;
          return (
            <g key={label}>
              <line
                x1={x}
                y1={HEADER_HEIGHT}
                x2={x}
                y2={totalHeight - 20}
                stroke="#e5e7eb"
                strokeWidth={1}
              />
              <text x={x + 4} y={22} fontSize={11} fill="#6b7280" fontWeight={500}>
                {label}
              </text>
            </g>
          );
        })}

        {/* Header separator */}
        <line
          x1={0}
          y1={HEADER_HEIGHT}
          x2={totalWidth}
          y2={HEADER_HEIGHT}
          stroke="#d1d5db"
          strokeWidth={1}
        />

        {/* Member label column separator */}
        <line
          x1={LABEL_WIDTH}
          y1={0}
          x2={LABEL_WIDTH}
          y2={totalHeight}
          stroke="#d1d5db"
          strokeWidth={1}
        />

        {/* Rows */}
        {data.rows.map((row, rowIndex) => {
          const y = HEADER_HEIGHT + rowIndex * ROW_HEIGHT;
          const barY = y + (ROW_HEIGHT - BAR_HEIGHT) / 2;

          return (
            <g key={row.memberId}>
              {/* Row zebra stripe */}
              {rowIndex % 2 === 1 && (
                <rect
                  x={0}
                  y={y}
                  width={totalWidth}
                  height={ROW_HEIGHT}
                  fill="#f9fafb"
                />
              )}

              {/* Member name */}
              <text
                x={12}
                y={y + ROW_HEIGHT / 2 + 4}
                fontSize={13}
                fill="#111827"
                fontWeight={500}
              >
                {row.memberName}
              </text>

              {/* Allocation bars */}
              {row.allocations.map((a) => {
                const x1 = xForDate(a.periodStart);
                const x2 = xForDate(a.periodEnd);
                const w = Math.max(4, x2 - x1);
                const color = colorFor(a.projectId);
                return (
                  <g key={a.id}>
                    <title>
                      {a.projectName} / {a.skillName} — {a.percentage}%
                      {'\n'}
                      {a.periodStart} → {a.periodEnd}
                    </title>
                    <rect
                      x={x1}
                      y={barY}
                      width={w}
                      height={BAR_HEIGHT}
                      rx={4}
                      fill={color}
                      opacity={0.85}
                    />
                    {w > 60 && (
                      <text
                        x={x1 + 8}
                        y={barY + BAR_HEIGHT / 2 + 4}
                        fontSize={11}
                        fill="#ffffff"
                        fontWeight={600}
                      >
                        {a.projectName.length > 14
                          ? `${a.projectName.slice(0, 12)}…`
                          : a.projectName}{' '}
                        {a.percentage}%
                      </text>
                    )}
                  </g>
                );
              })}
            </g>
          );
        })}
      </svg>
    </div>
  );
}
