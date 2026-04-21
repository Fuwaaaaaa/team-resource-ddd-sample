'use client';

import { useState } from 'react';
import { AppHeader } from '@/components/layout/AppHeader';
import {
  useMemberAllocations,
  useCreateAllocation,
  useRevokeAllocation,
} from '@/features/allocations/api';
import { useMembers } from '@/features/members/api';
import { useProjects } from '@/features/projects/api';
import { useSkills } from '@/features/skills/api';
import { usePermissions } from '@/features/auth/api';
import { HttpError } from '@/lib/http';

export default function AllocationsPage() {
  const { canWrite } = usePermissions();
  const members = useMembers();
  const projects = useProjects();
  const skills = useSkills();

  const [memberId, setMemberId] = useState('');
  const allocations = useMemberAllocations(memberId || null);
  const createAlloc = useCreateAllocation();
  const revokeAlloc = useRevokeAllocation();

  const [form, setForm] = useState({
    projectId: '',
    skillId: '',
    percentage: '50',
    start: '2026-04-01',
    end: '2026-09-30',
  });
  const [error, setError] = useState<string | null>(null);

  const onCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    if (!memberId) {
      setError('Select a member first.');
      return;
    }
    try {
      await createAlloc.mutateAsync({
        memberId,
        projectId: form.projectId,
        skillId: form.skillId,
        allocationPercentage: Number(form.percentage),
        periodStart: form.start,
        periodEnd: form.end,
      });
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
        <h1 className="text-2xl font-bold text-gray-900">Allocations</h1>

        <div className="flex items-end gap-3 p-4 bg-white rounded-lg border border-gray-200">
          <div>
            <label className="block text-xs font-medium text-gray-700 mb-1">Member</label>
            <select
              value={memberId}
              onChange={(e) => setMemberId(e.target.value)}
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

        {canWrite && <form
          onSubmit={onCreate}
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
              type="submit"
              disabled={createAlloc.isPending}
              className="px-4 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50"
            >
              Create allocation
            </button>
            {error && <span className="text-sm text-red-600">{error}</span>}
          </div>
        </form>}

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
