'use client';

import Link from 'next/link';
import { useAuditLogs } from '@/features/auditLogs/api';
import { usePermissions } from '@/features/auth/api';
import type { AuditLogDto } from '@/features/auditLogs/types';

const EVENT_COLORS: Record<string, string> = {
  AllocationCreated: 'bg-green-50 text-green-700 border-green-200',
  AllocationRevoked: 'bg-gray-100 text-gray-600 border-gray-300',
  MemberCreated: 'bg-blue-50 text-blue-700 border-blue-200',
  MemberSkillUpdated: 'bg-indigo-50 text-indigo-700 border-indigo-200',
  ProjectRequirementChanged: 'bg-amber-50 text-amber-700 border-amber-200',
};

function humanSummary(log: AuditLogDto): string {
  const who = log.user?.name ?? 'System';
  switch (log.event_type) {
    case 'AllocationCreated': {
      const pct = typeof log.payload.percentage === 'number' ? `${log.payload.percentage}%` : '';
      return `${who} created an allocation ${pct}`;
    }
    case 'AllocationRevoked':
      return `${who} revoked an allocation`;
    case 'MemberCreated':
      return `${who} created a member`;
    case 'MemberSkillUpdated': {
      const lvl = typeof log.payload.proficiency === 'number' ? ` (lv ${log.payload.proficiency})` : '';
      return `${who} updated a member skill${lvl}`;
    }
    case 'ProjectRequirementChanged':
      return `${who} updated a project requirement`;
    default:
      return `${who} performed ${log.event_type}`;
  }
}

function timeAgo(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime();
  const m = Math.floor(diff / 60000);
  if (m < 1) return 'just now';
  if (m < 60) return `${m}m ago`;
  const h = Math.floor(m / 60);
  if (h < 24) return `${h}h ago`;
  const d = Math.floor(h / 24);
  return `${d}d ago`;
}

export function RecentActivityFeed({ limit = 5 }: { limit?: number }) {
  const { canViewAuditLog } = usePermissions();
  const { data, isLoading } = useAuditLogs({ perPage: limit });

  if (!canViewAuditLog) return null;

  return (
    <section className="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
      <div className="flex items-baseline justify-between mb-3">
        <h2 className="text-sm font-semibold text-gray-700">Recent activity</h2>
        <Link href="/audit-logs" className="text-xs text-blue-600 hover:underline">
          View all →
        </Link>
      </div>
      {isLoading && <p className="text-xs text-gray-500">Loading…</p>}
      {data && data.data.length === 0 && (
        <p className="text-xs text-gray-500">No recent activity.</p>
      )}
      <ul className="space-y-1.5">
        {data?.data.map((log) => (
          <li key={log.id} className="flex items-center gap-3 text-sm">
            <span
              className={`inline-flex px-1.5 py-0.5 text-[10px] font-medium rounded border ${
                EVENT_COLORS[log.event_type] ?? 'bg-gray-100 text-gray-600 border-gray-300'
              }`}
            >
              {log.event_type}
            </span>
            <span className="flex-1 text-gray-700 truncate">{humanSummary(log)}</span>
            <span className="text-xs text-gray-400 tabular-nums shrink-0">
              {timeAgo(log.created_at)}
            </span>
          </li>
        ))}
      </ul>
    </section>
  );
}
