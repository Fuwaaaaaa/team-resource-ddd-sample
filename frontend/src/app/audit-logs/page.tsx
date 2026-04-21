'use client';

import { useState } from 'react';
import { AppHeader } from '@/components/layout/AppHeader';
import { ExportButton } from '@/components/atoms/ExportButton';
import { useAuditLogs } from '@/features/auditLogs/api';
import type { AuditLogFilters } from '@/features/auditLogs/types';

const AGGREGATE_TYPES: Array<AuditLogFilters['aggregateType'] | ''> = [
  '',
  'allocation',
  'member',
  'project',
];

const EVENT_TYPES = [
  '',
  'AllocationCreated',
  'AllocationRevoked',
  'MemberCreated',
  'MemberSkillUpdated',
  'ProjectRequirementChanged',
];

export default function AuditLogsPage() {
  const [filters, setFilters] = useState<AuditLogFilters>({ perPage: 50 });
  const logs = useAuditLogs(filters);

  const fmtDate = (s: string) => new Date(s).toLocaleString('ja-JP');

  return (
    <>
      <AppHeader />
      <div className="max-w-[1400px] mx-auto px-4 py-8 space-y-4">
        <div className="flex items-baseline justify-between">
          <h1 className="text-2xl font-bold text-gray-900">Audit logs</h1>
          <div className="flex items-center gap-3">
            {logs.data && (
              <span className="text-xs text-gray-500">
                Showing {logs.data.data.length} of {logs.data.meta.total}
              </span>
            )}
            <ExportButton path="/api/export/audit-logs" filename="audit-logs.csv" />
          </div>
        </div>

        <div className="flex items-end gap-3 p-4 bg-white rounded-lg border border-gray-200">
          <div>
            <label className="block text-xs font-medium text-gray-700 mb-1">Aggregate</label>
            <select
              value={filters.aggregateType ?? ''}
              onChange={(e) =>
                setFilters((f) => ({
                  ...f,
                  aggregateType: (e.target.value || undefined) as AuditLogFilters['aggregateType'],
                }))
              }
              className="px-3 py-1.5 text-sm border border-gray-300 rounded-md min-w-[140px]"
            >
              {AGGREGATE_TYPES.map((t) => (
                <option key={t} value={t}>
                  {t === '' ? '— All —' : t}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-700 mb-1">Event type</label>
            <select
              value={filters.eventType ?? ''}
              onChange={(e) =>
                setFilters((f) => ({ ...f, eventType: e.target.value || undefined }))
              }
              className="px-3 py-1.5 text-sm border border-gray-300 rounded-md min-w-[220px]"
            >
              {EVENT_TYPES.map((t) => (
                <option key={t} value={t}>
                  {t === '' ? '— All —' : t}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-700 mb-1">Aggregate ID</label>
            <input
              type="text"
              placeholder="uuid..."
              value={filters.aggregateId ?? ''}
              onChange={(e) =>
                setFilters((f) => ({ ...f, aggregateId: e.target.value || undefined }))
              }
              className="px-3 py-1.5 text-sm border border-gray-300 rounded-md font-mono w-80"
            />
          </div>
        </div>

        <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-600">
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
                  <td colSpan={5} className="px-4 py-6 text-center text-gray-500">
                    Loading…
                  </td>
                </tr>
              )}
              {logs.data?.data.length === 0 && !logs.isLoading && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-gray-500">
                    No audit logs match the filters.
                  </td>
                </tr>
              )}
              {logs.data?.data.map((log) => (
                <tr key={log.id} className="border-t border-gray-100 align-top">
                  <td className="px-4 py-2 text-xs text-gray-600 whitespace-nowrap">
                    {fmtDate(log.created_at)}
                  </td>
                  <td className="px-4 py-2 text-xs text-gray-700 whitespace-nowrap">
                    {log.user ? (
                      <>
                        <div className="font-medium">{log.user.name}</div>
                        <div className="text-gray-400">{log.user.email}</div>
                      </>
                    ) : (
                      <span className="text-gray-400">— system —</span>
                    )}
                  </td>
                  <td className="px-4 py-2">
                    <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded bg-blue-50 text-blue-700">
                      {log.event_type}
                    </span>
                  </td>
                  <td className="px-4 py-2 text-xs">
                    <div className="font-medium text-gray-700">{log.aggregate_type}</div>
                    <div className="font-mono text-[10px] text-gray-400 truncate max-w-[200px]">
                      {log.aggregate_id}
                    </div>
                  </td>
                  <td className="px-4 py-2">
                    {Object.keys(log.payload).length > 0 ? (
                      <pre className="text-[11px] font-mono text-gray-600 whitespace-pre-wrap break-all bg-gray-50 rounded px-2 py-1 max-w-md">
                        {JSON.stringify(log.payload, null, 2)}
                      </pre>
                    ) : (
                      <span className="text-xs text-gray-400">(empty)</span>
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
