import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/lib/http';
import type { AuditLogFilters, AuditLogListResponse } from './types';

export const auditLogKeys = {
  all: ['auditLogs'] as const,
  list: (filters: AuditLogFilters) => [...auditLogKeys.all, filters] as const,
};

export function useAuditLogs(filters: AuditLogFilters = {}) {
  const params = new URLSearchParams();
  if (filters.aggregateType) params.set('aggregateType', filters.aggregateType);
  if (filters.aggregateId) params.set('aggregateId', filters.aggregateId);
  if (filters.eventType) params.set('eventType', filters.eventType);
  if (filters.perPage) params.set('perPage', String(filters.perPage));
  const qs = params.toString();

  return useQuery({
    queryKey: auditLogKeys.list(filters),
    queryFn: async () => {
      return await apiFetch<AuditLogListResponse>(
        `/api/audit-logs${qs ? `?${qs}` : ''}`,
      );
    },
    staleTime: 30 * 1000,
  });
}
