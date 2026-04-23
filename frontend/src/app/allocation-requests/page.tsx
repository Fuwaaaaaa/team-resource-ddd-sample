'use client';

import { useMemo, useState } from 'react';
import { AppHeader } from '@/components/layout/AppHeader';
import { AllocationRequestForm } from '@/components/molecules/AllocationRequestForm';
import { usePermissions } from '@/features/auth/api';
import {
  useAllocationRequests,
  useApproveAllocationRequest,
  useRejectAllocationRequest,
} from '@/features/allocationRequests/api';
import type {
  AllocationChangeRequestDto,
  ChangeRequestStatus,
} from '@/features/allocationRequests/types';

const STATUSES: Array<{ value: ChangeRequestStatus | 'all'; label: string }> = [
  { value: 'all', label: 'All' },
  { value: 'pending', label: 'Pending' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
];

function StatusBadge({ status }: { status: ChangeRequestStatus }) {
  const cls =
    status === 'pending'
      ? 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800'
      : status === 'approved'
        ? 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-800'
        : 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-800';
  return (
    <span className={`inline-block text-[11px] font-medium px-2 py-0.5 rounded border ${cls}`}>
      {status}
    </span>
  );
}

function PayloadPreview({ req }: { req: AllocationChangeRequestDto }) {
  if (req.type === 'create_allocation') {
    const p = req.payload as Record<string, unknown>;
    return (
      <div className="text-xs text-fg-muted space-y-0.5">
        <div>
          <span className="text-fg-muted/70">Member:</span> {String(p.memberId).slice(0, 8)}
        </div>
        <div>
          <span className="text-fg-muted/70">Project:</span> {String(p.projectId).slice(0, 8)}
        </div>
        <div>
          <span className="text-fg-muted/70">Skill:</span> {String(p.skillId).slice(0, 8)} / {String(p.allocationPercentage)}% / {String(p.periodStart)}〜{String(p.periodEnd)}
        </div>
      </div>
    );
  }
  const p = req.payload as Record<string, unknown>;
  return (
    <div className="text-xs text-fg-muted">
      Allocation ID: {String(p.allocationId).slice(0, 8)}
    </div>
  );
}

export default function AllocationRequestsPage() {
  const { role } = usePermissions();
  const [statusFilter, setStatusFilter] = useState<ChangeRequestStatus | 'all'>('pending');

  const effectiveStatus = statusFilter === 'all' ? undefined : statusFilter;
  const requests = useAllocationRequests(effectiveStatus);

  const approve = useApproveAllocationRequest();
  const reject = useRejectAllocationRequest();

  const canDecide = role === 'admin';

  const sorted = useMemo(() => {
    return [...(requests.data ?? [])].sort(
      (a, b) => new Date(b.requestedAt).getTime() - new Date(a.requestedAt).getTime(),
    );
  }, [requests.data]);

  const onApprove = async (id: string) => {
    const note = window.prompt('承認メモ (任意)') ?? undefined;
    await approve.mutateAsync({ id, note });
  };
  const onReject = async (id: string) => {
    const note = window.prompt('却下理由 (任意)') ?? undefined;
    await reject.mutateAsync({ id, note });
  };

  return (
    <>
      <AppHeader />
      <div className="max-w-[1200px] mx-auto px-4 py-8 space-y-4">
        <div className="flex items-baseline justify-between flex-wrap gap-2">
          <div>
            <h1 className="text-2xl font-bold text-fg">Allocation 変更申請</h1>
            <p className="text-xs text-fg-muted mt-1">
              {canDecide
                ? 'admin として全ての申請を閲覧・承認/却下できます。'
                : '自分が提出した申請を閲覧できます。承認/却下は admin のみ可能です。'}
            </p>
          </div>
          <div className="flex items-center gap-1 flex-wrap">
            {STATUSES.map((s) => (
              <button
                key={s.value}
                onClick={() => setStatusFilter(s.value)}
                className={`px-2.5 py-1 text-xs rounded-full border transition-colors ${
                  statusFilter === s.value
                    ? 'bg-primary/10 border-primary text-primary font-medium'
                    : 'bg-surface border-border text-fg-muted hover:bg-surface-muted'
                }`}
              >
                {s.label}
              </button>
            ))}
          </div>
        </div>

        <AllocationRequestForm />

        {requests.isLoading && (
          <div className="h-40 bg-surface-muted animate-pulse rounded-lg" />
        )}

        {requests.isError && (
          <div className="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-sm text-red-700 dark:text-red-300">
            申請一覧の取得に失敗しました。
          </div>
        )}

        {requests.data && sorted.length === 0 && (
          <div className="py-10 text-center text-sm text-fg-muted bg-surface rounded-lg border border-border">
            該当する申請はありません。
          </div>
        )}

        {sorted.length > 0 && (
          <div className="bg-surface rounded-lg border border-border divide-y divide-border">
            {sorted.map((req) => (
              <div key={req.id} className="p-4 flex flex-col sm:flex-row items-start gap-4">
                <div className="flex-shrink-0 sm:w-32">
                  <StatusBadge status={req.status} />
                  <div className="text-[11px] text-fg-muted mt-1">
                    {new Date(req.requestedAt).toLocaleString('ja-JP')}
                  </div>
                  <div className="text-[11px] text-fg-muted/70 mt-0.5">
                    by user #{req.requestedBy}
                  </div>
                </div>
                <div className="flex-1">
                  <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-fg">
                      {req.type === 'create_allocation' ? '新規アサイン' : 'アサイン解除'}
                    </span>
                    <code className="text-[10px] text-fg-muted/70">{req.id.slice(0, 8)}</code>
                  </div>
                  <div className="mt-1">
                    <PayloadPreview req={req} />
                  </div>
                  {req.reason && (
                    <div className="mt-2 text-xs text-fg bg-surface-muted p-2 rounded">
                      理由: {req.reason}
                    </div>
                  )}
                  {req.decisionNote && (
                    <div className="mt-2 text-xs text-fg bg-primary/10 p-2 rounded">
                      判断メモ: {req.decisionNote}
                    </div>
                  )}
                  {req.resultingAllocationId && (
                    <div className="mt-1 text-[11px] text-fg-muted">
                      → Allocation: <code>{req.resultingAllocationId.slice(0, 8)}</code>
                    </div>
                  )}
                </div>
                {canDecide && req.status === 'pending' && (
                  <div className="flex-shrink-0 flex gap-2">
                    <button
                      onClick={() => onApprove(req.id)}
                      disabled={approve.isPending}
                      className="px-3 py-1.5 text-xs font-medium bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50"
                    >
                      承認
                    </button>
                    <button
                      onClick={() => onReject(req.id)}
                      disabled={reject.isPending}
                      className="px-3 py-1.5 text-xs font-medium bg-red-600 text-white rounded hover:bg-red-700 disabled:opacity-50"
                    >
                      却下
                    </button>
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
    </>
  );
}
