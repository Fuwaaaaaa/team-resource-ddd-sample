'use client';

import { useState } from 'react';
import { useMembers } from '@/features/members/api';
import { useProjects } from '@/features/projects/api';
import { useSkills } from '@/features/skills/api';
import { useSubmitAllocationRequest } from '@/features/allocationRequests/api';
import { useTranslation } from '@/lib/i18n/useTranslation';

export function AllocationRequestForm() {
  const members = useMembers();
  const projects = useProjects();
  const skills = useSkills();
  const submit = useSubmitAllocationRequest();
  const t = useTranslation();

  const [memberId, setMemberId] = useState('');
  const [projectId, setProjectId] = useState('');
  const [skillId, setSkillId] = useState('');
  const [percentage, setPercentage] = useState(50);
  const [periodStart, setPeriodStart] = useState('');
  const [periodEnd, setPeriodEnd] = useState('');
  const [reason, setReason] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const canSubmit = memberId && projectId && skillId && periodStart && periodEnd && percentage > 0;

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setSuccess(null);
    try {
      const dto = await submit.mutateAsync({
        type: 'create_allocation',
        payload: {
          memberId,
          projectId,
          skillId,
          allocationPercentage: percentage,
          periodStart,
          periodEnd,
        },
        reason: reason || null,
      });
      setSuccess(t('request.submitted', { id: dto.id.slice(0, 8) }));
      setReason('');
    } catch (err) {
      setError(err instanceof Error ? err.message : String(err));
    }
  };

  return (
    <section className="bg-white rounded-lg border border-gray-200 p-4">
      <h2 className="text-sm font-semibold text-gray-800 mb-3">{t('request.formTitle')}</h2>
      <form onSubmit={onSubmit} className="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
        <label className="flex flex-col gap-1">
          <span className="text-gray-600">{t('request.member')}</span>
          <select
            value={memberId}
            onChange={(e) => setMemberId(e.target.value)}
            className="px-2 py-1.5 border border-gray-300 rounded"
          >
            <option value="">{t('request.selectPlaceholder')}</option>
            {members.data?.map((m) => (
              <option key={m.id} value={m.id}>
                {m.name}
              </option>
            ))}
          </select>
        </label>
        <label className="flex flex-col gap-1">
          <span className="text-gray-600">{t('request.project')}</span>
          <select
            value={projectId}
            onChange={(e) => setProjectId(e.target.value)}
            className="px-2 py-1.5 border border-gray-300 rounded"
          >
            <option value="">{t('request.selectPlaceholder')}</option>
            {projects.data?.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}
              </option>
            ))}
          </select>
        </label>
        <label className="flex flex-col gap-1">
          <span className="text-gray-600">{t('request.skill')}</span>
          <select
            value={skillId}
            onChange={(e) => setSkillId(e.target.value)}
            className="px-2 py-1.5 border border-gray-300 rounded"
          >
            <option value="">{t('request.selectPlaceholder')}</option>
            {skills.data?.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name}
              </option>
            ))}
          </select>
        </label>
        <label className="flex flex-col gap-1">
          <span className="text-gray-600">{t('request.percentage')}</span>
          <input
            type="number"
            min={1}
            max={100}
            value={percentage}
            onChange={(e) => setPercentage(Number(e.target.value))}
            className="px-2 py-1.5 border border-gray-300 rounded"
          />
        </label>
        <label className="flex flex-col gap-1">
          <span className="text-gray-600">{t('request.startDate')}</span>
          <input
            type="date"
            value={periodStart}
            onChange={(e) => setPeriodStart(e.target.value)}
            className="px-2 py-1.5 border border-gray-300 rounded"
          />
        </label>
        <label className="flex flex-col gap-1">
          <span className="text-gray-600">{t('request.endDate')}</span>
          <input
            type="date"
            value={periodEnd}
            onChange={(e) => setPeriodEnd(e.target.value)}
            className="px-2 py-1.5 border border-gray-300 rounded"
          />
        </label>
        <label className="flex flex-col gap-1 md:col-span-2">
          <span className="text-gray-600">{t('request.reason')}</span>
          <textarea
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            rows={2}
            maxLength={500}
            className="px-2 py-1.5 border border-gray-300 rounded"
          />
        </label>
        <div className="md:col-span-2 flex items-center justify-between">
          <div className="text-xs">
            {error && <span className="text-red-700">{error}</span>}
            {success && <span className="text-green-700">{success}</span>}
          </div>
          <button
            type="submit"
            disabled={!canSubmit || submit.isPending}
            className="px-4 py-1.5 text-xs font-medium bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
          >
            {submit.isPending ? t('request.submitting') : t('request.submit')}
          </button>
        </div>
      </form>
    </section>
  );
}
