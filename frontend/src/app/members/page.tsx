'use client';

import { useState } from 'react';
import { AppHeader } from '@/components/layout/AppHeader';
import {
  useMembers,
  useCreateMember,
  useDeleteMember,
  useUpsertMemberSkill,
} from '@/features/members/api';
import { useSkills } from '@/features/skills/api';
import { usePermissions } from '@/features/auth/api';
import { HttpError } from '@/lib/http';

export default function MembersPage() {
  const { canWrite } = usePermissions();
  const members = useMembers();
  const skills = useSkills();
  const createMember = useCreateMember();
  const deleteMember = useDeleteMember();
  const upsertSkill = useUpsertMemberSkill();
  const [name, setName] = useState('');
  const [hours, setHours] = useState('8');
  const [error, setError] = useState<string | null>(null);

  const skillMap = new Map((skills.data ?? []).map((s) => [s.id, s.name] as const));

  const onCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    try {
      await createMember.mutateAsync({
        name: name.trim(),
        standardWorkingHours: Number(hours),
      });
      setName('');
      setHours('8');
    } catch (err) {
      setError(err instanceof HttpError ? err.message : 'Failed to create member.');
    }
  };

  const onAddSkill = async (memberId: string) => {
    const skillId = window.prompt(
      'Skill ID (use /skills list):\n\n' +
        (skills.data ?? []).map((s) => `${s.id}  ${s.name}`).join('\n'),
    );
    if (!skillId) return;
    const lvl = window.prompt('Proficiency (1-5)', '3');
    if (!lvl) return;
    try {
      await upsertSkill.mutateAsync({
        memberId,
        skillId: skillId.trim(),
        proficiency: Number(lvl),
      });
    } catch (err) {
      alert(err instanceof HttpError ? err.message : 'Failed to update skill.');
    }
  };

  return (
    <>
      <AppHeader />
      <div className="max-w-[1400px] mx-auto px-4 py-8 space-y-6">
        <h1 className="text-2xl font-bold text-gray-900">Members</h1>

        {canWrite ? (
          <form
            onSubmit={onCreate}
            className="flex items-end gap-3 p-4 bg-white rounded-lg border border-gray-200"
          >
            <div>
              <label className="block text-xs font-medium text-gray-700 mb-1">Name</label>
              <input
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="px-3 py-1.5 text-sm border border-gray-300 rounded-md w-64"
              />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-700 mb-1">
                Standard hours/day
              </label>
              <input
                type="number"
                step="0.5"
                min="0.5"
                max="24"
                required
                value={hours}
                onChange={(e) => setHours(e.target.value)}
                className="px-3 py-1.5 text-sm border border-gray-300 rounded-md w-32"
              />
            </div>
            <button
              type="submit"
              disabled={createMember.isPending}
              className="px-4 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50"
            >
              Create
            </button>
            {error && <span className="text-sm text-red-600">{error}</span>}
          </form>
        ) : (
          <p className="text-xs text-gray-500 italic">Read-only: sign in as admin or manager to create members.</p>
        )}

        <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-600">
              <tr>
                <th className="px-4 py-2 text-left font-medium">Name</th>
                <th className="px-4 py-2 text-left font-medium">Hours/day</th>
                <th className="px-4 py-2 text-left font-medium">Skills</th>
                <th className="px-4 py-2 text-right font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              {members.isLoading && (
                <tr><td colSpan={4} className="px-4 py-6 text-center text-gray-500">Loading…</td></tr>
              )}
              {members.data?.map((m) => (
                <tr key={m.id} className="border-t border-gray-100">
                  <td className="px-4 py-2">{m.name}</td>
                  <td className="px-4 py-2">{m.standardWorkingHours}</td>
                  <td className="px-4 py-2">
                    <div className="flex flex-wrap gap-1">
                      {m.skills.map((s) => (
                        <span
                          key={s.id}
                          className="inline-flex items-center gap-1 px-2 py-0.5 text-xs bg-gray-100 rounded-full"
                        >
                          {skillMap.get(s.skillId) ?? s.skillId}
                          <span className="font-semibold text-blue-700">{s.proficiency}</span>
                        </span>
                      ))}
                    </div>
                  </td>
                  <td className="px-4 py-2 text-right space-x-2">
                    {canWrite ? (
                      <>
                        <button
                          onClick={() => onAddSkill(m.id)}
                          className="px-2 py-1 text-xs text-blue-700 hover:bg-blue-50 rounded"
                        >
                          Add / update skill
                        </button>
                        <button
                          onClick={() => {
                            if (confirm(`Delete member ${m.name}?`)) deleteMember.mutate(m.id);
                          }}
                          className="px-2 py-1 text-xs text-red-600 hover:bg-red-50 rounded"
                        >
                          Delete
                        </button>
                      </>
                    ) : (
                      <span className="text-xs text-gray-400">—</span>
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
