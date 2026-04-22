'use client';

import { Suspense, useCallback, useMemo, useState } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { AppHeader } from '@/components/layout/AppHeader';
import { useProjectKpis, useProjects } from '@/features/projects/api';
import {
  PROJECT_STATUS_LABELS,
  type ProjectKpiDto,
  type ProjectStatus,
} from '@/features/projects/types';

const STATUS_BADGE: Record<ProjectStatus, string> = {
  planning: 'bg-amber-100 text-amber-800',
  active: 'bg-green-100 text-green-800',
  completed: 'bg-gray-100 text-gray-600',
  canceled: 'bg-red-100 text-red-700',
};

function todayYmd(): string {
  const d = new Date();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${d.getFullYear()}-${m}-${day}`;
}

function fulfillmentTone(rate: number): 'good' | 'warn' | 'bad' {
  if (rate >= 90) return 'good';
  if (rate >= 60) return 'warn';
  return 'bad';
}

function toneClasses(tone: 'default' | 'good' | 'warn' | 'bad'): string {
  return tone === 'good'
    ? 'text-green-700'
    : tone === 'warn'
      ? 'text-amber-700'
      : tone === 'bad'
        ? 'text-red-700'
        : 'text-gray-900';
}

function GapBadge({ gap }: { gap: number }) {
  if (gap > 0) {
    return <span className="inline-flex items-center px-1.5 py-0.5 text-[10px] rounded-full bg-blue-100 text-blue-800">+{gap}</span>;
  }
  if (gap < 0) {
    return <span className="inline-flex items-center px-1.5 py-0.5 text-[10px] rounded-full bg-red-100 text-red-800">{gap}</span>;
  }
  return <span className="inline-flex items-center px-1.5 py-0.5 text-[10px] rounded-full bg-gray-100 text-gray-600">0</span>;
}

function KpiCard({
  kpi,
  loading,
  error,
}: {
  kpi?: ProjectKpiDto;
  loading: boolean;
  error?: unknown;
}) {
  if (loading) {
    return (
      <div className="flex-1 min-w-[260px] bg-white border border-gray-200 rounded-lg shadow-sm p-4">
        <p className="text-sm text-gray-500">Loading…</p>
      </div>
    );
  }
  if (error || !kpi) {
    return (
      <div className="flex-1 min-w-[260px] bg-white border border-red-200 rounded-lg shadow-sm p-4">
        <p className="text-sm text-red-700">Failed to load KPI.</p>
      </div>
    );
  }

  const tone = fulfillmentTone(kpi.fulfillmentRate);
  return (
    <div className="flex-1 min-w-[260px] max-w-[360px] bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-3">
      <div className="flex items-center justify-between gap-2">
        <Link
          href={`/projects/${kpi.projectId}/kpi`}
          className="font-semibold text-gray-900 hover:text-indigo-700 truncate"
          title={kpi.projectName}
        >
          {kpi.projectName}
        </Link>
        <span
          className={`inline-flex items-center px-2 py-0.5 text-xs rounded-full ${STATUS_BADGE[kpi.status]}`}
        >
          {PROJECT_STATUS_LABELS[kpi.status]}
        </span>
      </div>

      <div className="grid grid-cols-2 gap-2 text-sm">
        <div>
          <div className="text-xs text-gray-500">充足率</div>
          <div className={`text-xl font-bold ${toneClasses(tone)}`}>
            {kpi.fulfillmentRate.toFixed(1)}%
          </div>
          <div className="text-[11px] text-gray-500">
            {kpi.totalQualifiedHeadcount} / {kpi.totalRequiredHeadcount} seats
          </div>
        </div>
        <div>
          <div className="text-xs text-gray-500">アクティブ</div>
          <div className="text-xl font-bold text-gray-900">
            {kpi.activeAllocationCount}
          </div>
          <div className="text-[11px] text-gray-500">件</div>
        </div>
        <div>
          <div className="text-xs text-gray-500">延べ人月</div>
          <div className="text-xl font-bold text-gray-900">
            {kpi.personMonthsAllocated.toFixed(2)}
          </div>
        </div>
        <div>
          <div className="text-xs text-gray-500">30 日以内終了</div>
          <div
            className={`text-xl font-bold ${kpi.upcomingEnds.length > 0 ? 'text-amber-700' : 'text-gray-900'}`}
          >
            {kpi.upcomingEnds.length}
          </div>
        </div>
      </div>

      <div>
        <div className="text-xs text-gray-500 mb-1">必要スキル</div>
        {kpi.requiredSkillsBreakdown.length === 0 ? (
          <p className="text-xs text-gray-400 italic">未定義</p>
        ) : (
          <ul className="space-y-0.5 text-xs">
            {kpi.requiredSkillsBreakdown.map((r) => (
              <li
                key={r.skillId}
                className="flex items-center justify-between gap-2 border-b border-gray-100 last:border-b-0 py-0.5"
              >
                <span className="truncate">{r.skillName}</span>
                <span className="text-gray-500">
                  {r.qualifiedHeadcount}/{r.requiredHeadcount}
                </span>
                <GapBadge gap={r.gap} />
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}

function ProjectsCompareContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [referenceDate, setReferenceDate] = useState<string>(todayYmd());
  const projects = useProjects();

  const selectedIds = useMemo(() => {
    const raw = searchParams.get('ids');
    if (!raw) return [] as string[];
    return raw.split(',').filter((s) => s.length > 0);
  }, [searchParams]);

  // 未指定時は全プロジェクト
  const effectiveIds = useMemo(() => {
    if (selectedIds.length > 0) return selectedIds;
    return (projects.data ?? []).map((p) => p.id);
  }, [selectedIds, projects.data]);

  const kpis = useProjectKpis(effectiveIds, referenceDate);

  const toggle = useCallback(
    (id: string) => {
      const current = new Set(
        selectedIds.length > 0
          ? selectedIds
          : (projects.data ?? []).map((p) => p.id),
      );
      if (current.has(id)) current.delete(id);
      else current.add(id);
      const next = Array.from(current);
      const qs = next.length === 0 ? '' : `?ids=${next.join(',')}`;
      router.replace(`/projects/compare${qs}`);
    },
    [router, selectedIds, projects.data],
  );

  return (
    <>
      <AppHeader />
      <div className="max-w-[1400px] mx-auto px-4 py-8 space-y-6">
        <div className="flex items-center gap-3 text-sm text-gray-500">
          <Link href="/projects" className="hover:underline">
            ← Projects
          </Link>
        </div>

        <div className="flex items-baseline justify-between">
          <h1 className="text-2xl font-bold text-gray-900">プロジェクト比較</h1>
          <div className="flex items-center gap-2">
            <label htmlFor="ref-date" className="text-xs font-medium text-gray-600">
              基準日
            </label>
            <input
              id="ref-date"
              type="date"
              value={referenceDate}
              onChange={(e) => setReferenceDate(e.target.value)}
              className="px-2 py-1 text-sm border border-gray-300 rounded-md"
            />
          </div>
        </div>

        <section className="bg-white border border-gray-200 rounded-lg shadow-sm p-4">
          <h2 className="text-sm font-semibold text-gray-800 mb-2">
            対象プロジェクト
          </h2>
          {projects.isLoading ? (
            <p className="text-sm text-gray-500">Loading…</p>
          ) : (
            <div className="flex flex-wrap gap-2">
              {(projects.data ?? []).map((p) => {
                const isSelected =
                  selectedIds.length === 0 || selectedIds.includes(p.id);
                return (
                  <button
                    key={p.id}
                    type="button"
                    onClick={() => toggle(p.id)}
                    className={`px-3 py-1 text-xs rounded-full border transition ${
                      isSelected
                        ? 'bg-indigo-600 text-white border-indigo-600'
                        : 'bg-white text-gray-700 border-gray-300 hover:border-indigo-400'
                    }`}
                  >
                    {p.name}
                  </button>
                );
              })}
            </div>
          )}
          {selectedIds.length === 0 && (projects.data?.length ?? 0) > 0 && (
            <p className="text-[11px] text-gray-500 mt-2 italic">
              全プロジェクトを表示中(クリックで除外、URL に同期されます)
            </p>
          )}
        </section>

        <section className="flex flex-wrap gap-4">
          {effectiveIds.length === 0 && (
            <p className="text-sm text-gray-500">比較するプロジェクトを選択してください。</p>
          )}
          {kpis.map((q, i) => (
            <KpiCard
              key={effectiveIds[i]}
              kpi={q.data}
              loading={q.isLoading}
              error={q.error}
            />
          ))}
        </section>
      </div>
    </>
  );
}

export default function ProjectsComparePage() {
  return (
    <Suspense fallback={<p className="text-sm text-gray-500 p-8">Loading…</p>}>
      <ProjectsCompareContent />
    </Suspense>
  );
}
