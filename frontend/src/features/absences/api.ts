import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiFetch } from '@/lib/http';
import { dashboardKeys } from '@/features/dashboard/api';
import type { AbsenceDto, AbsenceType } from './types';

export const absenceKeys = {
  all: ['absences'] as const,
  byMember: (memberId: string) => [...absenceKeys.all, 'member', memberId] as const,
};

export function useAbsencesByMember(memberId: string | null) {
  return useQuery({
    queryKey: absenceKeys.byMember(memberId ?? ''),
    queryFn: async () => {
      const res = await apiFetch<{ data: AbsenceDto[] }>(
        `/api/members/${memberId}/absences`,
      );
      return res.data;
    },
    enabled: !!memberId,
    staleTime: 30 * 1000,
  });
}

export interface RegisterAbsenceInput {
  memberId: string;
  startDate: string;
  endDate: string;
  type: AbsenceType;
  note?: string;
}

export function useRegisterAbsence() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: RegisterAbsenceInput) => {
      const res = await apiFetch<{ data: AbsenceDto }>('/api/absences', {
        method: 'POST',
        body: JSON.stringify(input),
      });
      return res.data;
    },
    onSuccess: (_absence, input) => {
      qc.invalidateQueries({ queryKey: absenceKeys.byMember(input.memberId) });
      qc.invalidateQueries({ queryKey: dashboardKeys.all });
    },
  });
}

export function useCancelAbsence() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (absenceId: string) => {
      const res = await apiFetch<{ data: AbsenceDto }>(
        `/api/absences/${absenceId}/cancel`,
        { method: 'POST' },
      );
      return res.data;
    },
    onSuccess: (absence) => {
      qc.invalidateQueries({ queryKey: absenceKeys.byMember(absence.memberId) });
      qc.invalidateQueries({ queryKey: dashboardKeys.all });
    },
  });
}
