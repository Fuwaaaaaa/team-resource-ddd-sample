import { useMutation, useQueries, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiFetch } from '@/lib/http';
import { dashboardKeys } from '@/features/dashboard/api';
import { allocationKeys } from '@/features/allocations/api';
import type { ProjectDto, ProjectKpiDto, ProjectStatus } from './types';

export const projectKeys = {
  all: ['projects'] as const,
  list: () => [...projectKeys.all, 'list'] as const,
  kpi: (id: string, referenceDate?: string) =>
    [...projectKeys.all, 'kpi', id, referenceDate ?? 'today'] as const,
};

export function useProjectKpi(projectId: string, referenceDate?: string) {
  return useQuery({
    queryKey: projectKeys.kpi(projectId, referenceDate),
    queryFn: async () => {
      const qs = referenceDate ? `?referenceDate=${encodeURIComponent(referenceDate)}` : '';
      const res = await apiFetch<{ data: ProjectKpiDto }>(`/api/projects/${projectId}/kpi${qs}`);
      return res.data;
    },
    enabled: projectId.length > 0,
    staleTime: 30 * 1000,
  });
}

/** 複数プロジェクトの KPI を並列 fetch する */
export function useProjectKpis(projectIds: string[], referenceDate?: string) {
  return useQueries({
    queries: projectIds.map((id) => ({
      queryKey: projectKeys.kpi(id, referenceDate),
      queryFn: async () => {
        const qs = referenceDate ? `?referenceDate=${encodeURIComponent(referenceDate)}` : '';
        const res = await apiFetch<{ data: ProjectKpiDto }>(`/api/projects/${id}/kpi${qs}`);
        return res.data;
      },
      enabled: id.length > 0,
      staleTime: 30 * 1000,
    })),
  });
}

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

export function useChangeProjectStatus() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: { projectId: string; status: ProjectStatus }) => {
      const res = await apiFetch<{ data: ProjectDto }>(
        `/api/projects/${input.projectId}/status`,
        {
          method: 'POST',
          body: JSON.stringify({ status: input.status }),
        },
      );
      return res.data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: projectKeys.all });
      qc.invalidateQueries({ queryKey: dashboardKeys.all });
      // 完了時にアロケーションが auto-revoke されるので一覧も再取得
      qc.invalidateQueries({ queryKey: allocationKeys.all });
    },
  });
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
