'use client';

import { useState } from 'react';
import { AppHeader } from '@/components/layout/AppHeader';
import {
  useMemberAllocations,
  useCreateAllocation,
  useRevokeAllocation,
  useSimulateAllocation,
} from '@/features/allocations/api';
import { useAllocationSuggestions } from '@/features/allocations/suggestions';
import { useMembers } from '@/features/members/api';
import { useProjects } from '@/features/projects/api';
import { useSkills } from '@/features/skills/api';
import { usePermissions } from '@/features/auth/api';
import { ExportButton } from '@/components/atoms/ExportButton';
import { HttpError } from '@/lib/http';
import type { AllocationSimulationDto } from '@/features/allocations/types';

export default function AllocationsPage() {
  const { canWrite } = usePermissions();
  const members = useMembers();
  const projects = useProjects();
  const skills = useSkills();

  const [memberId, setMemberId] = useState('');
  const allocations = useMemberAllocations(memberId || null);
  const createAlloc = useCreateAllocation();
  const revokeAlloc = useRevokeAllocation();
  const simulate = useSimulateAllocation();

  const [form, setForm] = useState({
    projectId: '',
    skillId: '',
    percentage: '50',
    start: '2026-04-01',
    end: '2026-09-30',
  });
  const [error, setError] = useState<string | null>(null);
  const [simulation, setSimulation] = useState<AllocationSimulationDto | null>(null);
  const [minProficiency, setMinProficiency] = useState(3);
  const [showSuggestions, setShowSuggestions] = useState(false);

  const suggestions = useAllocationSuggestions(
    showSuggestions && form.projectId && form.skillId && form.start
      ? {
          projectId: form.projectId,
          skillId: form.skillId,
          minimumProficiency: minProficiency,
          periodStart: form.start,
          limit: 5,
        }
      : null,
  );

  const buildInput = () => ({
    memberId,
    projectId: form.projectId,
    skillId: form.skillId,
    allocationPercentage: Number(form.percentage),
    periodStart: form.start,
    periodEnd: form.end,
  });

  const validate = (): boolean => {
    if (!memberId) {
      setError('Select a member first.');
      return false;
    }
    return true;
  };

  const onSimulate = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setSimulation(null);
    if (!validate()) return;
    try {
      const result = await simulate.mutateAsync(buildInput());
      setSimulation(result);
    } catch (err) {
      setError(err instanceof HttpError ? err.message : 'Simulation failed.');
    }
  };

  const onApply = async () => {
    setError(null);
    if (!validate()) return;
    try {
      await createAlloc.mutateAsync(buildInput());
      setSimulation(null);
    } catch (err) {
      setError(err instanceof HttpError ? err.message : 'Failed.');
    }
  };

  const projectName = (id: string) =>
    projects.data?.find((p) => p.id === id)?.name ?? id;
  const skillName = (id: string) =>
    skills.data?.find((s) => s.id === id)?.name ?? id;

  return (
    <>
      <AppHeader />
      <div className="max-w-[1400px] mx-auto px-4 py-8 space-y-6">
        <div className="flex items-baseline justify-between">
          <h1 className="text-2xl font-bold text-gray-900">Allocations</h1>
          <ExportButton path="/api/export/allocations" filename="allocations.csv" />
        </div>

        <div className="flex items-end gap-3 p-4 bg-white rounded-lg border border-gray-200">
          <div>
            <label className="block text-xs font-medium text-gray-700 mb-1">Member</label>
            <select
              value={memberId}
              onChange={(e) => {
                setMemberId(e.target.value);
                setSimulation(null);
              }}
              className="px-3 py-1.5 text-sm border border-gray-300 rounded-md w-64"
            >
              <option value="">— select —</option>
              {members.data?.map((m) => (
                <option key={m.id} value={m.id}>{m.name}</option>
              ))}
            </select>
          </div>
        </div>

        {!canWrite && (
          <p className="text-xs text-gray-500 italic">
            Read-only: sign in as admin or manager to create or revoke allocations.
          </p>
        )}

        {canWrite && (
          <form
            onSubmit={onSimulate}
            className="grid grid-cols-6 gap-3 p-4 bg-white rounded-lg border border-gray-200"
          >
            <div className="col-span-2">
              <label className="block text-xs font-medium text-gray-700 mb-1">Project</label>
              <select
                required
                value={form.projectId}
                onChange={(e) => setForm({ ...form, projectId: e.target.value })}
                className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md"
              >
                <option value="">—</option>
                {projects.data?.map((p) => (
                  <option key={p.id} value={p.id}>{p.name}</option>
                ))}
              </select>
            </div>
            <div className="col-span-2">
              <label className="block text-xs font-medium text-gray-700 mb-1">Skill role</label>
              <select
                required
                value={form.skillId}
                onChange={(e) => setForm({ ...form, skillId: e.target.value })}
                className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md"
              >
                <option value="">—</option>
                {skills.data?.map((s) => (
                  <option key={s.id} value={s.id}>{s.name}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-700 mb-1">%</label>
              <input
                type="number" min="1" max="100" required
                value={form.percentage}
                onChange={(e) => setForm({ ...form, percentage: e.target.value })}
                className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md"
              />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-700 mb-1">Start</label>
              <input
                type="date" required
                value={form.start}
                onChange={(e) => setForm({ ...form, start: e.target.value })}
                className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md"
              />
            </div>
            <div className="col-span-5" />
            <div>
              <label className="block text-xs font-medium text-gray-700 mb-1">End</label>
              <input
                type="date" required
                value={form.end}
                onChange={(e) => setForm({ ...form, end: e.target.value })}
                className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md"
              />
            </div>
            <div className="col-span-6 flex items-center gap-3">
              <button
                type="button"
                onClick={() => setShowSuggestions((v) => !v)}
                className="px-4 py-1.5 text-sm font-medium text-purple-700 bg-purple-50 border border-purple-200 rounded-md hover:bg-purple-100"
              >
                {showSuggestions ? 'Hide suggestions' : 'Suggest candidates'}
              </button>
              <button
                type="submit"
                disabled={simulate.isPending}
                className="px-4 py-1.5 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 disabled:opacity-50"
              >
                {simulate.isPending ? 'Simulating…' : 'Simulate (what-if)'}
              </button>
              <button
                type="button"
                onClick={onApply}
                disabled={createAlloc.isPending}
                className="px-4 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50"
              >
                Create allocation
              </button>
              {error && <span className="text-sm text-red-600">{error}</span>}
            </div>
          </form>
        )}

        {showSuggestions && canWrite && (
          <div className="p-4 bg-white rounded-lg border-2 border-purple-200 shadow-sm space-y-3">
            <div className="flex items-center justify-between">
              <h2 className="text-sm font-semibold text-purple-800">
                Assignment candidates (top {suggestions.data?.length ?? 0})
              </h2>
              <div className="flex items-center gap-2 text-xs text-gray-600">
                <label>Min proficiency:</label>
                <select
                  value={minProficiency}
                  onChange={(e) => setMinProficiency(Number(e.target.value))}
                  className="px-2 py-1 text-xs border border-gray-300 rounded"
                >
                  {[1, 2, 3, 4, 5].map((l) => (
                    <option key={l} value={l}>
                      ≥ {l}
                    </option>
                  ))}
                </select>
              </div>
            </div>
            {(!form.projectId || !form.skillId) && (
              <p className="text-xs text-gray-500">
                Select project, skill, and start date above to see candidates.
              </p>
            )}
            {suggestions.isLoading && (
              <p className="text-xs text-gray-500">Loading candidates…</p>
            )}
            {suggestions.data && suggestions.data.length === 0 && (
              <p className="text-xs text-gray-500">
                該当する候補がいません。条件を緩めてみてください。
              </p>
            )}
            <ul className="space-y-2">
              {suggestions.data?.map((c) => (
                <li
                  key={c.memberId}
                  className="flex items-center justify-between gap-3 p-3 border border-gray-200 rounded-md hover:bg-purple-50"
                >
                  <div className="flex-1 min-w-0">
                    <div className="font-medium text-sm text-gray-900">
                      {c.memberName}
                      <span className="ml-2 text-xs text-gray-500 font-normal">
                        score {c.score.toFixed(0)}
                      </span>
                    </div>
                    <div className="text-xs text-gray-600 mt-0.5">
                      {c.reasons.join(' · ')}
                    </div>
                  </div>
                  <button
                    onClick={() => {
                      setMemberId(c.memberId);
                      setShowSuggestions(false);
                    }}
                    className="px-3 py-1 text-xs font-medium text-purple-700 bg-purple-100 rounded hover:bg-purple-200"
                  >
                    Pick
                  </button>
                </li>
              ))}
            </ul>
          </div>
        )}

        {simulation && (
          <div className="p-4 bg-white rounded-lg border-2 border-blue-200 shadow-sm space-y-3">
            <div className="flex items-center justify-between">
              <h2 className="text-sm font-semibold text-blue-800">
                Simulation result (not saved)
              </h2>
              <button
                onClick={() => setSimulation(null)}
                className="text-xs text-gray-500 hover:text-gray-700"
                aria-label="Close simulation"
              >
                ✕
              </button>
            </div>
            <div className="grid grid-cols-4 gap-4 text-sm">
              <Stat label="Current allocated" value={`${simulation.currentTotalPercentage}%`} />
              <Stat
                label="Projected allocated"
                value={`${simulation.projectedTotalPercentage}%`}
                accent={simulation.projectedOverloaded ? 'danger' : 'primary'}
              />
              <Stat
                label="Projected free"
                value={`${simulation.projectedAvailablePercentage}%`}
                accent="success"
              />
              <Stat
                label="Overload"
                value={
                  simulation.projectedOverloaded
                    ? `+${simulation.projectedOverloadHours.toFixed(1)}h/day`
                    : 'None'
                }
                accent={simulation.projectedOverloaded ? 'danger' : 'muted'}
              />
            </div>
            <div className="pt-3 border-t border-gray-200 flex items-center gap-3">
              <button
                onClick={onApply}
                disabled={createAlloc.isPending}
                className="px-4 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50"
              >
                Apply for real
              </button>
              <span className="text-xs text-gray-500">
                Adjust the form and click Simulate again to re-evaluate.
              </span>
            </div>
          </div>
        )}

        <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-600">
              <tr>
                <th className="px-4 py-2 text-left font-medium">Project</th>
                <th className="px-4 py-2 text-left font-medium">Skill</th>
                <th className="px-4 py-2 text-left font-medium">%</th>
                <th className="px-4 py-2 text-left font-medium">Period</th>
                <th className="px-4 py-2 text-left font-medium">Status</th>
                <th className="px-4 py-2 text-right font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              {!memberId && (
                <tr><td colSpan={6} className="px-4 py-6 text-center text-gray-500">Select a member to view allocations.</td></tr>
              )}
              {allocations.data?.map((a) => (
                <tr key={a.id} className="border-t border-gray-100">
                  <td className="px-4 py-2">{projectName(a.projectId)}</td>
                  <td className="px-4 py-2">{skillName(a.skillId)}</td>
                  <td className="px-4 py-2">{a.allocationPercentage}%</td>
                  <td className="px-4 py-2 text-gray-600">
                    {a.periodStart} → {a.periodEnd}
                  </td>
                  <td className="px-4 py-2">
                    <span className={a.status === 'active' ? 'text-green-700' : 'text-gray-400'}>
                      {a.status}
                    </span>
                  </td>
                  <td className="px-4 py-2 text-right">
                    {a.status === 'active' && canWrite && (
                      <button
                        onClick={() => revokeAlloc.mutate(a.id)}
                        className="px-2 py-1 text-xs text-red-600 hover:bg-red-50 rounded"
                      >
                        Revoke
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </>
  );
}

function Stat({
  label,
  value,
  accent = 'default',
}: {
  label: string;
  value: string;
  accent?: 'default' | 'primary' | 'success' | 'danger' | 'muted';
}) {
  const colors: Record<string, string> = {
    default: 'text-gray-900',
    primary: 'text-blue-700',
    success: 'text-green-700',
    danger: 'text-red-700',
    muted: 'text-gray-500',
  };
  return (
    <div>
      <div className="text-[11px] font-medium text-gray-500 uppercase tracking-wider">
        {label}
      </div>
      <div className={`text-xl font-bold tabular-nums ${colors[accent]}`}>{value}</div>
    </div>
  );
}
