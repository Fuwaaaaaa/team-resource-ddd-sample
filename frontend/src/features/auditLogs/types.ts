export interface AuditLogUserSummary {
  id: number;
  name: string;
  email: string;
}

export interface AuditLogDto {
  id: string;
  user_id: number | null;
  user?: AuditLogUserSummary | null;
  event_type: string;
  aggregate_type: 'allocation' | 'member' | 'project';
  aggregate_id: string;
  payload: Record<string, unknown>;
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
  aggregateType?: 'allocation' | 'member' | 'project';
  aggregateId?: string;
  eventType?: string;
  perPage?: number;
}
