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
      ? 'bg-amber-100 text-amber-800 border-amber-200'
      : status === 'approved'
        ? 'bg-green-100 text-green-800 border-green-200'
        : 'bg-red-100 text-red-800 border-red-200';
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
      <div className="text-xs text-gray-600 space-y-0.5">
        <div>
          <span className="text-gray-400">Member:</span> {String(p.memberId).slice(0, 8)}
        </div>
        <div>
          <span className="text-gray-400">Project:</span> {String(p.projectId).slice(0, 8)}
        </div>
        <div>
          <span className="text-gray-400">Skill:</span> {String(p.skillId).slice(0, 8)} / {String(p.allocationPercentage)}% / {String(p.periodStart)}〜{String(p.periodEnd)}
        </div>
      </div>
    );
  }
  const p = req.payload as Record<string, unknown>;
  return (
    <div className="text-xs text-gray-600">
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
        <div className="flex items-baseline justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Allocation 変更申請</h1>
            <p className="text-xs text-gray-500 mt-1">
              {canDecide
                ? 'admin として全ての申請を閲覧・承認/却下できます。'
                : '自分が提出した申請を閲覧できます。承認/却下は admin のみ可能です。'}
            </p>
          </div>
          <div className="flex items-center gap-1">
            {STATUSES.map((s) => (
              <button
                key={s.value}
                onClick={() => setStatusFilter(s.value)}
                className={`px-2.5 py-1 text-xs rounded-full border transition-colors ${
                  statusFilter === s.value
                    ? 'bg-blue-100 border-blue-400 text-blue-700 font-medium'
                    : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-50'
                }`}
              >
                {s.label}
              </button>
            ))}
          </div>
        </div>

        <AllocationRequestForm />

        {requests.isLoading && (
          <div className="h-40 bg-gray-100 animate-pulse rounded-lg" />
        )}

        {requests.isError && (
          <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            申請一覧の取得に失敗しました。
          </div>
        )}

        {requests.data && sorted.length === 0 && (
          <div className="py-10 text-center text-sm text-gray-500 bg-white rounded-lg border border-gray-200">
            該当する申請はありません。
          </div>
        )}

        {sorted.length > 0 && (
          <div className="bg-white rounded-lg border border-gray-200 divide-y divide-gray-100">
            {sorted.map((req) => (
              <div key={req.id} className="p-4 flex items-start gap-4">
                <div className="flex-shrink-0 w-32">
                  <StatusBadge status={req.status} />
                  <div className="text-[11px] text-gray-500 mt-1">
                    {new Date(req.requestedAt).toLocaleString('ja-JP')}
                  </div>
                  <div className="text-[11px] text-gray-400 mt-0.5">
                    by user #{req.requestedBy}
                  </div>
                </div>
                <div className="flex-1">
                  <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-gray-800">
                      {req.type === 'create_allocation' ? '新規アサイン' : 'アサイン解除'}
                    </span>
                    <code className="text-[10px] text-gray-400">{req.id.slice(0, 8)}</code>
                  </div>
                  <div className="mt-1">
                    <PayloadPreview req={req} />
                  </div>
                  {req.reason && (
                    <div className="mt-2 text-xs text-gray-700 bg-gray-50 p-2 rounded">
                      理由: {req.reason}
                    </div>
                  )}
                  {req.decisionNote && (
                    <div className="mt-2 text-xs text-gray-700 bg-blue-50 p-2 rounded">
                      判断メモ: {req.decisionNote}
                    </div>
                  )}
                  {req.resultingAllocationId && (
                    <div className="mt-1 text-[11px] text-gray-500">
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
