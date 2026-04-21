import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiFetch } from '@/lib/http';
import { dashboardKeys } from '@/features/dashboard/api';
import type { MemberDto } from './types';

export const memberKeys = {
  all: ['members'] as const,
  list: () => [...memberKeys.all, 'list'] as const,
  detail: (id: string) => [...memberKeys.all, id] as const,
};

export function useMembers() {
  return useQuery({
    queryKey: memberKeys.list(),
    queryFn: async () => {
      const res = await apiFetch<{ data: MemberDto[] }>('/api/members');
      return res.data;
    },
    staleTime: 60 * 1000,
  });
}

export interface CreateMemberInput {
  name: string;
  standardWorkingHours?: number;
}

export function useCreateMember() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateMemberInput) => {
      const res = await apiFetch<{ data: MemberDto }>('/api/members', {
        method: 'POST',
        body: JSON.stringify(input),
      });
      return res.data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: memberKeys.all });
      qc.invalidateQueries({ queryKey: dashboardKeys.all });
    },
  });
}

export function useDeleteMember() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => {
      await apiFetch<void>(`/api/members/${id}`, { method: 'DELETE' });
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: memberKeys.all });
      qc.invalidateQueries({ queryKey: dashboardKeys.all });
    },
  });
}

export interface UpsertMemberSkillInput {
  memberId: string;
  skillId: string;
  proficiency: number;
}

export function useUpsertMemberSkill() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: UpsertMemberSkillInput) => {
      const res = await apiFetch<{ data: MemberDto }>(
        `/api/members/${input.memberId}/skills/${input.skillId}`,
        {
          method: 'PUT',
          body: JSON.stringify({ proficiency: input.proficiency }),
        },
      );
      return res.data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: memberKeys.all });
      qc.invalidateQueries({ queryKey: dashboardKeys.all });
    },
  });
}
