'use client';

import { useMemo, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { AppHeader } from '@/components/layout/AppHeader';
import { useProjectKpi } from '@/features/projects/api';
import { PROJECT_STATUS_LABELS, type ProjectStatus } from '@/features/projects/types';
import { HttpError } from '@/lib/http';

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

function SummaryCard({
  label,
  value,
  sub,
  tone,
}: {
  label: string;
  value: string;
  sub?: string;
  tone?: 'default' | 'good' | 'warn' | 'bad';
}) {
  const toneClasses =
    tone === 'good'
      ? 'text-green-700'
      : tone === 'warn'
        ? 'text-amber-700'
        : tone === 'bad'
          ? 'text-red-700'
          : 'text-gray-900';
  return (
    <div className="flex-1 min-w-[160px] bg-white border border-gray-200 rounded-lg shadow-sm px-4 py-3">
      <div className="text-xs text-gray-500">{label}</div>
      <div className={`text-2xl font-bold mt-1 ${toneClasses}`}>{value}</div>
      {sub && <div className="text-xs text-gray-500 mt-0.5">{sub}</div>}
    </div>
  );
}

function FulfillmentTone(rate: number): 'good' | 'warn' | 'bad' {
  if (rate >= 90) return 'good';
  if (rate >= 60) return 'warn';
  return 'bad';
}

function GapBadge({ gap }: { gap: number }) {
  if (gap > 0) {
    return (
      <span className="inline-flex items-center px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800">
        +{gap}
      </span>
    );
  }
  if (gap < 0) {
    return (
      <span className="inline-flex items-center px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-800">
        {gap}
      </span>
    );
  }
  return (
    <span className="inline-flex items-center px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">
      0
    </span>
  );
}

function DaysRemainingBadge({ days }: { days: number }) {
  const tone =
    days <= 7 ? 'bg-red-100 text-red-800' : days <= 14 ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700';
  return (
    <span className={`inline-flex items-center px-2 py-0.5 text-xs rounded-full ${tone}`}>
      残 {days} 日
    </span>
  );
}

export default function ProjectKpiPage() {
  const params = useParams<{ id: string }>();
  const projectId = params.id;
  const [referenceDate, setReferenceDate] = useState<string>(todayYmd());
  const kpi = useProjectKpi(projectId, referenceDate);

  const fulfillmentTone = useMemo(
    () => (kpi.data ? FulfillmentTone(kpi.data.fulfillmentRate) : 'default'),
    [kpi.data],
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

        {kpi.isLoading && <p className="text-sm text-gray-500">Loading…</p>}

        {kpi.isError && (
          <div className="p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            {kpi.error instanceof HttpError ? kpi.error.message : 'Failed to load KPI.'}
          </div>
        )}

        {kpi.data && (
          <>
            <div className="flex items-baseline justify-between">
              <div className="flex items-center gap-3">
                <h1 className="text-2xl font-bold text-gray-900">{kpi.data.projectName}</h1>
                <span
                  className={`inline-flex items-center px-2 py-0.5 text-xs rounded-full ${STATUS_BADGE[kpi.data.status]}`}
                >
                  {PROJECT_STATUS_LABELS[kpi.data.status]}
                </span>
              </div>
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

            <div className="flex flex-wrap gap-3">
              <SummaryCard
                label="総合充足率"
                value={`${kpi.data.fulfillmentRate.toFixed(1)}%`}
                sub={`${kpi.data.totalQualifiedHeadcount} / ${kpi.data.totalRequiredHeadcount} seats`}
                tone={fulfillmentTone}
              />
              <SummaryCard
                label="アクティブアサイン数"
                value={String(kpi.data.activeAllocationCount)}
              />
              <SummaryCard
                label="延べ人月"
                value={kpi.data.personMonthsAllocated.toFixed(2)}
                sub="稼働期間日数 × % / 30"
              />
              <SummaryCard
                label="30 日以内に終了"
                value={String(kpi.data.upcomingEnds.length)}
                tone={kpi.data.upcomingEnds.length > 0 ? 'warn' : 'default'}
                sub="次アサイン計画の注意"
              />
            </div>

            <section className="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
              <header className="px-4 py-2 border-b border-gray-200 bg-gray-50">
                <h2 className="text-sm font-semibold text-gray-800">必要スキル別 充足状況</h2>
              </header>
              {kpi.data.requiredSkillsBreakdown.length === 0 ? (
                <p className="px-4 py-6 text-sm text-gray-500">必要スキルが定義されていません。</p>
              ) : (
                <table className="w-full text-sm">
                  <thead className="text-gray-600">
                    <tr>
                      <th className="px-4 py-2 text-left font-medium">スキル</th>
                      <th className="px-4 py-2 text-right font-medium">必要</th>
                      <th className="px-4 py-2 text-right font-medium">適格</th>
                      <th className="px-4 py-2 text-right font-medium">ギャップ</th>
                    </tr>
                  </thead>
                  <tbody>
                    {kpi.data.requiredSkillsBreakdown.map((row) => (
                      <tr key={row.skillId} className="border-t border-gray-100">
                        <td className="px-4 py-2 font-medium">{row.skillName}</td>
                        <td className="px-4 py-2 text-right">{row.requiredHeadcount}</td>
                        <td className="px-4 py-2 text-right">{row.qualifiedHeadcount}</td>
                        <td className="px-4 py-2 text-right">
                          <GapBadge gap={row.gap} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </section>

            <section className="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
              <header className="px-4 py-2 border-b border-gray-200 bg-gray-50">
                <h2 className="text-sm font-semibold text-gray-800">
                  30 日以内に終了するアサイン
                </h2>
              </header>
              {kpi.data.upcomingEnds.length === 0 ? (
                <p className="px-4 py-6 text-sm text-gray-500">該当するアサインはありません。</p>
              ) : (
                <table className="w-full text-sm">
                  <thead className="text-gray-600">
                    <tr>
                      <th className="px-4 py-2 text-left font-medium">メンバー</th>
                      <th className="px-4 py-2 text-left font-medium">終了日</th>
                      <th className="px-4 py-2 text-left font-medium">残日数</th>
                    </tr>
                  </thead>
                  <tbody>
                    {kpi.data.upcomingEnds.map((row) => (
                      <tr key={row.allocationId} className="border-t border-gray-100">
                        <td className="px-4 py-2 font-medium">{row.memberName}</td>
                        <td className="px-4 py-2">{row.endDate}</td>
                        <td className="px-4 py-2">
                          <DaysRemainingBadge days={row.daysRemaining} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </section>
          </>
        )}
      </div>
    </>
  );
}
