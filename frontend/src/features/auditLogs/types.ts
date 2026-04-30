export interface AuditLogUserSummary {
  id: number;
  name: string;
  email: string;
}

export type AuditLogAggregateType =
  | 'allocation'
  | 'member'
  | 'project'
  | 'user'
  | 'absence'
  | 'allocation_change_request';

export interface AuditLogDto {
  id: string;
  user_id: number | null;
  user?: AuditLogUserSummary | null;
  event_type: string;
  aggregate_type: AuditLogAggregateType;
  aggregate_id: string;
  aggregate_label: string | null;
  payload: Record<string, unknown>;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string;
}

export interface AuditLogListResponse {
  data: AuditLogDto[];
  meta: {
    total: number;
    page: number;
    perPage: number;
    lastPage: number;
  };
}

export interface AuditLogFilters {
  aggregateType?: AuditLogAggregateType;
  aggregateId?: string;
  eventType?: string;
  from?: string; // ISO date (YYYY-MM-DD)
  to?: string;
  userId?: number;
  perPage?: number;
}
