'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { AppHeader } from '@/components/layout/AppHeader';
import { ExportButton } from '@/components/atoms/ExportButton';
import { useAuditLogs } from '@/features/auditLogs/api';
import type { AuditLogAggregateType, AuditLogDto, AuditLogFilters } from '@/features/auditLogs/types';

const AGGREGATE_TYPES: Array<AuditLogAggregateType | ''> = [
  '',
  'allocation',
  'member',
  'project',
  'user',
  'absence',
  'allocation_change_request',
];

const EVENT_TYPES = [
  '',
  'AllocationCreated',
  'AllocationRevoked',
  'MemberCreated',
  'MemberSkillUpdated',
  'ProjectRequirementChanged',
  'ProjectActivated',
  'ProjectCompleted',
  'ProjectCanceled',
  'AbsenceRegistered',
  'AbsenceCanceled',
  'AllocationChangeRequestSubmitted',
  'AllocationChangeRequestApproved',
  'AllocationChangeRequestRejected',
  'UserCreated',
  'UserRoleChanged',
  'UserPasswordReset',
];

/**
 * aggregate_type ごとに行クリック時の遷移先を返す。
 * 該当画面が無いタイプ (allocation / absence / allocation_change_request) は null。
 */
function navigateTargetFor(log: AuditLogDto): string | null {
  switch (log.aggregate_type) {
    case 'member':
      return `/members/${log.aggregate_id}/kpi`;
    case 'project':
      return `/projects/${log.aggregate_id}/kpi`;
    case 'user':
      return '/admin/users';
    default:
      return null;
  }
}

/**
 * `*Changed` 系イベントの payload に from / to が両方入っている場合は
 * `from → to` の差分表示にする。それ以外は raw JSON にフォールバック。
 */
function formatPayload(payload: Record<string, unknown>): { mode: 'diff' | 'json'; nodes: Array<{ key: string; from?: unknown; to?: unknown }>; raw?: string } {
  if (payload.from !== undefined && payload.to !== undefined) {
    return {
      mode: 'diff',
      nodes: [{ key: 'value', from: payload.from, to: payload.to }],
    };
  }
  return { mode: 'json', nodes: [], raw: JSON.stringify(payload, null, 2) };
}

export default function AuditLogsPage() {
  const router = useRouter();
  const [filters, setFilters] = useState<AuditLogFilters>({ perPage: 50 });
  const logs = useAuditLogs(filters);

  const fmtDate = (s: string) => new Date(s).toLocaleString('ja-JP');

  const updateFilter = <K extends keyof AuditLogFilters>(key: K, value: AuditLogFilters[K] | undefined) => {
    setFilters((f) => {
      if (value === undefined || value === '' || value === null) {
        const { [key]: _, ...rest } = f;
        return rest;
      }
      return { ...f, [key]: value };
    });
  };

  return (
    <>
      <AppHeader />
      <div className="max-w-[1400px] mx-auto px-4 py-8 space-y-4">
        <div className="flex items-baseline justify-between">
          <h1 className="text-2xl font-bold text-fg">Audit logs</h1>
          <div className="flex items-center gap-3">
            {logs.data && (
              <span className="text-xs text-fg-muted">
                Showing {logs.data.data.length} of {logs.data.meta.total}
              </span>
            )}
            <ExportButton path="/api/export/audit-logs" filename="audit-logs.csv" />
          </div>
        </div>

        <div className="flex flex-wrap items-end gap-3 p-4 bg-surface rounded-lg border border-border">
          <div>
            <label className="block text-xs font-medium text-fg-muted mb-1">Aggregate</label>
            <select
              value={filters.aggregateType ?? ''}
              onChange={(e) =>
                updateFilter('aggregateType', (e.target.value || undefined) as AuditLogFilters['aggregateType'])
              }
              className="px-3 py-1.5 text-sm border border-border bg-surface text-fg rounded-md min-w-[180px]"
            >
              {AGGREGATE_TYPES.map((t) => (
                <option key={t} value={t}>
                  {t === '' ? '— All —' : t}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-xs font-medium text-fg-muted mb-1">Event type</label>
            <select
              value={filters.eventType ?? ''}
              onChange={(e) => updateFilter('eventType', e.target.value || undefined)}
              className="px-3 py-1.5 text-sm border border-border bg-surface text-fg rounded-md min-w-[240px]"
            >
              {EVENT_TYPES.map((t) => (
                <option key={t} value={t}>
                  {t === '' ? '— All —' : t}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-xs font-medium text-fg-muted mb-1">From</label>
            <input
              type="date"
              value={filters.from ?? ''}
              onChange={(e) => updateFilter('from', e.target.value || undefined)}
              className="px-3 py-1.5 text-sm border border-border bg-surface text-fg rounded-md"
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-fg-muted mb-1">To</label>
            <input
              type="date"
              value={filters.to ?? ''}
              onChange={(e) => updateFilter('to', e.target.value || undefined)}
              className="px-3 py-1.5 text-sm border border-border bg-surface text-fg rounded-md"
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-fg-muted mb-1">Operator (user ID)</label>
            <input
              type="number"
              min={1}
              placeholder="user id"
              value={filters.userId ?? ''}
              onChange={(e) => updateFilter('userId', e.target.value ? Number(e.target.value) : undefined)}
              className="px-3 py-1.5 text-sm border border-border bg-surface text-fg rounded-md w-32"
            />
          </div>
          <div className="flex-grow" />
          <div>
            <label className="block text-xs font-medium text-fg-muted mb-1">Aggregate ID</label>
            <input
              type="text"
              placeholder="uuid..."
              value={filters.aggregateId ?? ''}
              onChange={(e) => updateFilter('aggregateId', e.target.value || undefined)}
              className="px-3 py-1.5 text-sm border border-border bg-surface text-fg rounded-md font-mono w-80"
            />
          </div>
        </div>

        <div className="bg-surface rounded-lg border border-border overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-surface-muted text-fg-muted">
              <tr>
                <th className="px-4 py-2 text-left font-medium">When</th>
                <th className="px-4 py-2 text-left font-medium">Who</th>
                <th className="px-4 py-2 text-left font-medium">Event</th>
                <th className="px-4 py-2 text-left font-medium">Aggregate</th>
                <th className="px-4 py-2 text-left font-medium">Payload</th>
              </tr>
            </thead>
            <tbody>
              {logs.isLoading && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-fg-muted">
                    Loading…
                  </td>
                </tr>
              )}
              {logs.data?.data.length === 0 && !logs.isLoading && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-fg-muted">
                    No audit logs match the filters.
                  </td>
                </tr>
              )}
              {logs.data?.data.map((log) => {
                const target = navigateTargetFor(log);
                const clickable = target !== null;
                const formatted = formatPayload(log.payload);

                return (
                  <tr
                    key={log.id}
                    onClick={clickable ? () => router.push(target!) : undefined}
                    className={`border-t border-border align-top ${clickable ? 'cursor-pointer hover:bg-surface-muted' : ''}`}
                    title={clickable ? `Open ${log.aggregate_type}` : undefined}
                  >
                    <td className="px-4 py-2 text-xs text-fg-muted whitespace-nowrap">
                      {fmtDate(log.created_at)}
                    </td>
                    <td className="px-4 py-2 text-xs text-fg whitespace-nowrap">
                      {log.user ? (
                        <>
                          <div className="font-medium">{log.user.name}</div>
                          <div className="text-fg-muted">{log.user.email}</div>
                          {log.ip_address && (
                            <div className="text-fg-muted text-[10px] font-mono">{log.ip_address}</div>
                          )}
                        </>
                      ) : (
                        <span className="text-fg-muted">— system —</span>
                      )}
                    </td>
                    <td className="px-4 py-2">
                      <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded bg-primary/10 text-primary">
                        {log.event_type}
                      </span>
                    </td>
                    <td className="px-4 py-2 text-xs">
                      <div className="font-medium text-fg">{log.aggregate_type}</div>
                      {log.aggregate_label && (
                        <div className="text-fg">{log.aggregate_label}</div>
                      )}
                      <div className="font-mono text-[10px] text-fg-muted truncate max-w-[200px]">
                        {log.aggregate_id}
                      </div>
                    </td>
                    <td className="px-4 py-2">
                      {formatted.mode === 'diff' ? (
                        <div className="text-xs font-mono">
                          <span className="text-danger">{String(formatted.nodes[0].from)}</span>
                          <span className="text-fg-muted px-1">→</span>
                          <span className="text-success">{String(formatted.nodes[0].to)}</span>
                        </div>
                      ) : Object.keys(log.payload).length > 0 ? (
                        <pre className="text-[11px] font-mono text-fg-muted whitespace-pre-wrap break-all bg-surface-muted rounded px-2 py-1 max-w-md">
                          {formatted.raw}
                        </pre>
                      ) : (
                        <span className="text-xs text-fg-muted">(empty)</span>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>
    </>
  );
}
