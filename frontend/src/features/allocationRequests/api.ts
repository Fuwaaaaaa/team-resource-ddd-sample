import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiFetch } from '@/lib/http';
import { allocationKeys } from '@/features/allocations/api';
import { dashboardKeys } from '@/features/dashboard/api';
import type {
  AllocationChangeRequestDto,
  ChangeRequestStatus,
  ChangeRequestType,
  CreateAllocationPayload,
  RevokeAllocationPayload,
} from './types';

export const allocationRequestKeys = {
  all: ['allocation-requests'] as const,
  list: (status?: ChangeRequestStatus) =>
    [...allocationRequestKeys.all, 'list', status ?? 'all'] as const,
} as const;

export function useAllocationRequests(status?: ChangeRequestStatus) {
  return useQuery({
    queryKey: allocationRequestKeys.list(status),
    queryFn: async () => {
      const qs = status ? `?status=${encodeURIComponent(status)}` : '';
      const res = await apiFetch<{ data: AllocationChangeRequestDto[] }>(
        `/api/allocation-requests${qs}`,
      );
      return res.data;
    },
    staleTime: 30 * 1000,
  });
}

export interface SubmitAllocationRequestInput {
  type: ChangeRequestType;
  payload: CreateAllocationPayload | RevokeAllocationPayload;
  reason?: string | null;
}

export function useSubmitAllocationRequest() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: SubmitAllocationRequestInput) => {
      const res = await apiFetch<{ data: AllocationChangeRequestDto }>(
        '/api/allocation-requests',
        { method: 'POST', body: JSON.stringify(input) },
      );
      return res.data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: allocationRequestKeys.all });
    },
  });
}

export function useApproveAllocationRequest() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, note }: { id: string; note?: string }) => {
      const res = await apiFetch<{ data: AllocationChangeRequestDto }>(
        `/api/allocation-requests/${encodeURIComponent(id)}/approve`,
        { method: 'POST', body: JSON.stringify({ note: note ?? null }) },
      );
      return res.data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: allocationRequestKeys.all });
      qc.invalidateQueries({ queryKey: allocationKeys.all });
      qc.invalidateQueries({ queryKey: dashboardKeys.all });
    },
  });
}

export function useRejectAllocationRequest() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, note }: { id: string; note?: string }) => {
      const res = await apiFetch<{ data: AllocationChangeRequestDto }>(
        `/api/allocation-requests/${encodeURIComponent(id)}/reject`,
        { method: 'POST', body: JSON.stringify({ note: note ?? null }) },
      );
      return res.data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: allocationRequestKeys.all });
    },
  });
}
