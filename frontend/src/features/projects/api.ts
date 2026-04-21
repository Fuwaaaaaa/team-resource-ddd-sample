import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiFetch } from '@/lib/http';
import { dashboardKeys } from '@/features/dashboard/api';
import type { ProjectDto } from './types';

export const projectKeys = {
  all: ['projects'] as const,
  list: () => [...projectKeys.all, 'list'] as const,
};

export function useProjects() {
  return useQuery({
    queryKey: projectKeys.list(),
    queryFn: async () => {
      const res = await apiFetch<{ data: ProjectDto[] }>('/api/projects');
      return res.data;
    },
    staleTime: 60 * 1000,
  });
}

export function useCreateProject() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: { name: string }) => {
      const res = await apiFetch<{ data: ProjectDto }>('/api/projects', {
        method: 'POST',
        body: JSON.stringify(input),
      });
      return res.data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: projectKeys.all });
      qc.invalidateQueries({ queryKey: dashboardKeys.all });
    },
  });
}

export function useDeleteProject() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => {
      await apiFetch<void>(`/api/projects/${id}`, { method: 'DELETE' });
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: projectKeys.all });
      qc.invalidateQueries({ queryKey: dashboardKeys.all });
    },
  });
}

export interface UpsertRequiredSkillInput {
  projectId: string;
  skillId: string;
  requiredProficiency: number;
  headcount: number;
}

export function useUpsertRequiredSkill() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: UpsertRequiredSkillInput) => {
      const res = await apiFetch<{ data: ProjectDto }>(
        `/api/projects/${input.projectId}/required-skills/${input.skillId}`,
        {
          method: 'PUT',
          body: JSON.stringify({
            requiredProficiency: input.requiredProficiency,
            headcount: input.headcount,
          }),
        },
      );
      return res.data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: projectKeys.all });
      qc.invalidateQueries({ queryKey: dashboardKeys.all });
    },
  });
}
