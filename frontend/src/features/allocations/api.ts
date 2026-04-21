import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiFetch } from '@/lib/http';
import { dashboardKeys } from '@/features/dashboard/api';
import type { AllocationDto } from './types';

export const allocationKeys = {
  all: ['allocations'] as const,
  byMember: (memberId: string) => [...allocationKeys.all, 'member', memberId] as const,
};

export function useMemberAllocations(memberId: string | null) {
  return useQuery({
    queryKey: allocationKeys.byMember(memberId ?? ''),
    queryFn: async () => {
      if (!memberId) return [];
      const res = await apiFetch<{ data: AllocationDto[] }>(
        `/api/allocations?memberId=${encodeURIComponent(memberId)}`,
      );
      return res.data;
    },
    enabled: Boolean(memberId),
  });
}

export interface CreateAllocationInput {
  memberId: string;
  projectId: string;
  skillId: string;
  allocationPercentage: number;
  periodStart: string;
  periodEnd: string;
}

export function useCreateAllocation() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateAllocationInput) => {
      const res = await apiFetch<{ data: AllocationDto }>('/api/allocations', {
        method: 'POST',
        body: JSON.stringify(input),
      });
      return res.data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: allocationKeys.all });
      qc.invalidateQueries({ queryKey: dashboardKeys.all });
    },
  });
}

export function useRevokeAllocation() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => {
      const res = await apiFetch<{ data: AllocationDto }>(
        `/api/allocations/${id}/revoke`,
        { method: 'POST' },
      );
      return res.data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: allocationKeys.all });
      qc.invalidateQueries({ queryKey: dashboardKeys.all });
    },
  });
}
