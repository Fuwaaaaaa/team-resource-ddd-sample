'use client';

import { useEffect } from 'react';
import { useMembers } from '@/features/members/api';
import { useProjects } from '@/features/projects/api';
import { useSkills } from '@/features/skills/api';
import { useMemberAllocations, useRevokeAllocation } from '@/features/allocations/api';

export interface MemberDetailModalProps {
  memberId: string | null;
  onClose: () => void;
}

export function MemberDetailModal({ memberId, onClose }: MemberDetailModalProps) {
  const members = useMembers();
  const projects = useProjects();
  const skills = useSkills();
  const allocations = useMemberAllocations(memberId);
  const revoke = useRevokeAllocation();

  const member = members.data?.find((m) => m.id === memberId);

  // ESC で閉じる
  useEffect(() => {
    if (!memberId) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [memberId, onClose]);

  if (!memberId) return null;

  const projectName = (id: string) => projects.data?.find((p) => p.id === id)?.name ?? id;
  const skillName = (id: string) => skills.data?.find((s) => s.id === id)?.name ?? id;
  const skillMap = new Map((skills.data ?? []).map((s) => [s.id, s.name] as const));

  const activeAllocations = (allocations.data ?? []).filter((a) => a.status === 'active');
  const totalAllocated = activeAllocations.reduce((sum, a) => sum + a.allocationPercentage, 0);
  const available = Math.max(0, 100 - totalAllocated);
  const isOverloaded = totalAllocated > 100;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="member-detail-title"
      onClick={onClose}
    >
      <div
        className="relative w-full max-w-2xl max-h-[90vh] overflow-y-auto bg-surface rounded-lg border border-border shadow-lg"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="sticky top-0 bg-surface border-b border-border px-6 py-4 flex items-center justify-between">
          <div>
            <h2 id="member-detail-title" className="text-lg font-semibold text-fg">
              {member?.name ?? 'Loading…'}
            </h2>
            {member && (
              <p className="text-xs text-fg-muted mt-0.5">
                Standard hours: {member.standardWorkingHours}h/day
              </p>
            )}
          </div>
          <button
            onClick={onClose}
            className="p-2 text-fg-muted hover:text-fg hover:bg-surface-muted rounded-md"
            aria-label="Close"
          >
            ✕
          </button>
        </div>

        <div className="px-6 py-4 space-y-6">
          {/* Capacity summary */}
          <section>
            <h3 className="text-xs font-semibold text-fg-muted uppercase tracking-wider mb-2">
              Capacity
            </h3>
            <div className="flex items-center gap-4 p-3 bg-surface-muted rounded-md">
              <div className="flex-1">
                <div className="flex items-baseline gap-2">
                  <span className={`text-2xl font-bold tabular-nums ${isOverloaded ? 'text-danger' : 'text-fg'}`}>
                    {totalAllocated}%
                  </span>
                  <span className="text-xs text-fg-muted">allocated</span>
                  <span className="ml-2 text-sm text-success tabular-nums">{available}% free</span>
                </div>
                <div className="w-full h-2 bg-border rounded-full overflow-hidden mt-2">
                  <div
                    className={`h-full ${isOverloaded ? 'bg-danger' : 'bg-primary'}`}
                    style={{ width: `${Math.min(100, totalAllocated)}%` }}
                  />
                </div>
              </div>
              {isOverloaded && (
                <span className="px-2 py-1 text-xs font-bold text-danger bg-danger-bg rounded">
                  OVERLOADED
                </span>
              )}
            </div>
          </section>

          {/* Skills */}
          <section>
            <h3 className="text-xs font-semibold text-fg-muted uppercase tracking-wider mb-2">
              Skills ({member?.skills.length ?? 0})
            </h3>
            <div className="flex flex-wrap gap-2">
              {member?.skills.map((s) => (
                <span
                  key={s.id}
                  className="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs bg-surface-muted rounded-full"
                >
                  {skillMap.get(s.skillId) ?? s.skillId}
                  <span className="font-semibold text-primary">{s.proficiency}/5</span>
                </span>
              ))}
              {member && member.skills.length === 0 && (
                <span className="text-xs text-fg-muted">No skills registered.</span>
              )}
            </div>
          </section>

          {/* Allocations */}
          <section>
            <h3 className="text-xs font-semibold text-fg-muted uppercase tracking-wider mb-2">
              Allocations ({allocations.data?.length ?? 0})
            </h3>
            {allocations.isLoading && (
              <p className="text-xs text-fg-muted">Loading allocations…</p>
            )}
            {allocations.data && allocations.data.length === 0 && (
              <p className="text-xs text-fg-muted">No allocations.</p>
            )}
            <ul className="space-y-2">
              {allocations.data?.map((a) => {
                const isActive = a.status === 'active';
                return (
                  <li
                    key={a.id}
                    className={`flex items-center justify-between gap-3 p-3 border border-border rounded-md ${
                      isActive ? 'bg-surface' : 'bg-surface-muted opacity-60'
                    }`}
                  >
                    <div className="flex-1 min-w-0">
                      <div className="font-medium text-sm text-fg truncate">
                        {projectName(a.projectId)}
                      </div>
                      <div className="text-xs text-fg-muted mt-0.5">
                        {skillName(a.skillId)} · {a.periodStart} → {a.periodEnd}
                      </div>
                    </div>
                    <span className="text-sm font-semibold text-primary tabular-nums shrink-0">
                      {a.allocationPercentage}%
                    </span>
                    <span
                      className={`text-[10px] font-semibold uppercase tracking-wider shrink-0 ${
                        isActive ? 'text-success' : 'text-fg-muted'
                      }`}
                    >
                      {a.status}
                    </span>
                    {isActive && (
                      <button
                        onClick={() => revoke.mutate(a.id)}
                        disabled={revoke.isPending}
                        className="px-2 py-1 text-xs text-danger hover:bg-danger-bg rounded shrink-0 disabled:opacity-50"
                      >
                        Revoke
                      </button>
                    )}
                  </li>
                );
              })}
            </ul>
          </section>
        </div>
      </div>
    </div>
  );
}
