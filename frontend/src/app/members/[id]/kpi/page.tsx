'use client';

import { useMemo, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { AppHeader } from '@/components/layout/AppHeader';
import { useMemberKpi } from '@/features/members/api';
import { HttpError } from '@/lib/http';

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

/** 稼働率 tone: 0-60=good (余裕) / 60-90=warn / 90-100=bad / >100=bad (過負荷) */
function UtilizationTone(rate: number): 'good' | 'warn' | 'bad' {
  if (rate > 100) return 'bad';
  if (rate >= 90) return 'bad';
  if (rate >= 60) return 'warn';
  return 'good';
}

function DaysRemainingBadge({ days }: { days: number }) {
  const tone =
    days <= 7
      ? 'bg-red-100 text-red-800'
      : days <= 14
        ? 'bg-amber-100 text-amber-800'
        : 'bg-gray-100 text-gray-700';
  return (
    <span className={`inline-flex items-center px-2 py-0.5 text-xs rounded-full ${tone}`}>
      残 {days} 日
    </span>
  );
}

function ProficiencyBadge({ level }: { level: number }) {
  const tone =
    level >= 4 ? 'bg-green-100 text-green-800' : level >= 3 ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-700';
  return (
    <span className={`inline-flex items-center px-2 py-0.5 text-xs rounded-full ${tone}`}>
      Lv {level}
    </span>
  );
}

export default function MemberKpiPage() {
  const params = useParams<{ id: string }>();
  const memberId = params.id;
  const [referenceDate, setReferenceDate] = useState<string>(todayYmd());
  const kpi = useMemberKpi(memberId, referenceDate);

  const utilizationTone = useMemo(
    () => (kpi.data ? UtilizationTone(kpi.data.currentUtilization) : 'default'),
    [kpi.data],
  );

  return (
    <>
      <AppHeader />
      <div className="max-w-[1400px] mx-auto px-4 py-8 space-y-6">
        <div className="flex items-center gap-3 text-sm text-gray-500">
          <Link href="/members" className="hover:underline">
            ← Members
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
                <h1 className="text-2xl font-bold text-gray-900">{kpi.data.memberName}</h1>
                {kpi.data.isOverloaded && (
                  <span className="inline-flex items-center px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-800">
                    過負荷
                  </span>
                )}
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
                label="現在の稼働率"
                value={`${kpi.data.currentUtilization}%`}
                sub={kpi.data.isOverloaded ? `+${kpi.data.currentUtilization - 100}% 超過` : undefined}
                tone={utilizationTone}
              />
              <SummaryCard
                label="残キャパシティ"
                value={`${kpi.data.remainingCapacity}%`}
                tone={kpi.data.remainingCapacity === 0 ? 'bad' : 'default'}
              />
              <SummaryCard
                label="アクティブアサイン数"
                value={String(kpi.data.activeAllocationCount)}
              />
              <SummaryCard
                label="30 日以内に終了"
                value={String(kpi.data.upcomingEnds.length)}
                sub="次アサイン計画の注意"
                tone={kpi.data.upcomingEnds.length > 0 ? 'warn' : 'default'}
              />
            </div>

            <section className="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
              <header className="px-4 py-2 border-b border-gray-200 bg-gray-50">
                <h2 className="text-sm font-semibold text-gray-800">アクティブアサイン</h2>
              </header>
              {kpi.data.activeAllocations.length === 0 ? (
                <p className="px-4 py-6 text-sm text-gray-500">アクティブなアサインはありません。</p>
              ) : (
                <table className="w-full text-sm">
                  <thead className="text-gray-600">
                    <tr>
                      <th className="px-4 py-2 text-left font-medium">プロジェクト</th>
                      <th className="px-4 py-2 text-left font-medium">スキル</th>
                      <th className="px-4 py-2 text-right font-medium">%</th>
                      <th className="px-4 py-2 text-left font-medium">期間</th>
                      <th className="px-4 py-2 text-left font-medium">残日数</th>
                    </tr>
                  </thead>
                  <tbody>
                    {kpi.data.activeAllocations.map((row) => (
                      <tr key={row.allocationId} className="border-t border-gray-100">
                        <td className="px-4 py-2 font-medium">
                          <Link
                            href={`/projects/${row.projectId}/kpi`}
                            className="text-indigo-700 hover:underline"
                          >
                            {row.projectName}
                          </Link>
                        </td>
                        <td className="px-4 py-2">{row.skillName}</td>
                        <td className="px-4 py-2 text-right">{row.percentage}%</td>
                        <td className="px-4 py-2 text-gray-600">
                          {row.startDate} → {row.endDate}
                        </td>
                        <td className="px-4 py-2">
                          <DaysRemainingBadge days={row.daysRemaining} />
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
                      <th className="px-4 py-2 text-left font-medium">プロジェクト</th>
                      <th className="px-4 py-2 text-left font-medium">終了日</th>
                      <th className="px-4 py-2 text-left font-medium">残日数</th>
                    </tr>
                  </thead>
                  <tbody>
                    {kpi.data.upcomingEnds.map((row) => (
                      <tr key={row.allocationId} className="border-t border-gray-100">
                        <td className="px-4 py-2 font-medium">
                          <Link
                            href={`/projects/${row.projectId}/kpi`}
                            className="text-indigo-700 hover:underline"
                          >
                            {row.projectName}
                          </Link>
                        </td>
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

            <section className="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
              <header className="px-4 py-2 border-b border-gray-200 bg-gray-50">
                <h2 className="text-sm font-semibold text-gray-800">保有スキル</h2>
              </header>
              {kpi.data.skills.length === 0 ? (
                <p className="px-4 py-6 text-sm text-gray-500">スキルが登録されていません。</p>
              ) : (
                <div className="px-4 py-3 flex flex-wrap gap-2">
                  {kpi.data.skills.map((s) => (
                    <span
                      key={s.skillId}
                      className="inline-flex items-center gap-2 px-2 py-1 text-xs rounded-full bg-gray-50 border border-gray-200"
                    >
                      {s.skillName}
                      <ProficiencyBadge level={s.proficiency} />
                    </span>
                  ))}
                </div>
              )}
            </section>
          </>
        )}
      </div>
    </>
  );
}
