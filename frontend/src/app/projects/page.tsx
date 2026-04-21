'use client';

import { useState } from 'react';
import { AppHeader } from '@/components/layout/AppHeader';
import {
  useProjects,
  useCreateProject,
  useDeleteProject,
  useUpsertRequiredSkill,
} from '@/features/projects/api';
import { useSkills } from '@/features/skills/api';
import { HttpError } from '@/lib/http';

export default function ProjectsPage() {
  const projects = useProjects();
  const skills = useSkills();
  const createProject = useCreateProject();
  const deleteProject = useDeleteProject();
  const upsertRequired = useUpsertRequiredSkill();
  const [name, setName] = useState('');
  const [error, setError] = useState<string | null>(null);

  const skillMap = new Map((skills.data ?? []).map((s) => [s.id, s.name] as const));

  const onCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    try {
      await createProject.mutateAsync({ name: name.trim() });
      setName('');
    } catch (err) {
      setError(err instanceof HttpError ? err.message : 'Failed.');
    }
  };

  const onAddRequired = async (projectId: string) => {
    const skillId = window.prompt(
      'Skill ID:\n\n' +
        (skills.data ?? []).map((s) => `${s.id}  ${s.name}`).join('\n'),
    );
    if (!skillId) return;
    const lvl = window.prompt('Required proficiency (1-5)', '3');
    const hc = window.prompt('Headcount', '1');
    if (!lvl || !hc) return;
    try {
      await upsertRequired.mutateAsync({
        projectId,
        skillId: skillId.trim(),
        requiredProficiency: Number(lvl),
        headcount: Number(hc),
      });
    } catch (err) {
      alert(err instanceof HttpError ? err.message : 'Failed.');
    }
  };

  return (
    <>
      <AppHeader />
      <div className="max-w-[1400px] mx-auto px-4 py-8 space-y-6">
        <h1 className="text-2xl font-bold text-gray-900">Projects</h1>

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
          <button
            type="submit"
            disabled={createProject.isPending}
            className="px-4 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50"
          >
            Create
          </button>
          {error && <span className="text-sm text-red-600">{error}</span>}
        </form>

        <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-600">
              <tr>
                <th className="px-4 py-2 text-left font-medium">Name</th>
                <th className="px-4 py-2 text-left font-medium">Required skills</th>
                <th className="px-4 py-2 text-right font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              {projects.data?.map((p) => (
                <tr key={p.id} className="border-t border-gray-100">
                  <td className="px-4 py-2 font-medium">{p.name}</td>
                  <td className="px-4 py-2">
                    <div className="flex flex-wrap gap-1">
                      {p.requiredSkills.map((rs) => (
                        <span
                          key={rs.id}
                          className="inline-flex items-center gap-1 px-2 py-0.5 text-xs bg-gray-100 rounded-full"
                        >
                          {skillMap.get(rs.skillId) ?? rs.skillId}
                          <span className="text-blue-700">≥{rs.requiredProficiency}</span>
                          <span className="text-gray-500">×{rs.headcount}</span>
                        </span>
                      ))}
                    </div>
                  </td>
                  <td className="px-4 py-2 text-right space-x-2">
                    <button
                      onClick={() => onAddRequired(p.id)}
                      className="px-2 py-1 text-xs text-blue-700 hover:bg-blue-50 rounded"
                    >
                      Add / update requirement
                    </button>
                    <button
                      onClick={() => {
                        if (confirm(`Delete project ${p.name}?`)) deleteProject.mutate(p.id);
                      }}
                      className="px-2 py-1 text-xs text-red-600 hover:bg-red-50 rounded"
                    >
                      Delete
                    </button>
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
