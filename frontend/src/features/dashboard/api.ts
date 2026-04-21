import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/lib/http';
import type {
  TeamCapacitySnapshotDto,
  OverloadAnalysisDto,
  SkillGapWarningListDto,
} from './types';

// --- Query Keys (centralized for cache invalidation) ---

export const dashboardKeys = {
  all: ['dashboard'] as const,
  capacity: (date: string) =>
    [...dashboardKeys.all, 'capacity', date] as const,
  overload: (date: string) =>
    [...dashboardKeys.all, 'overload', date] as const,
  skillGaps: (date: string, projectId?: string) =>
    [...dashboardKeys.all, 'skillGaps', date, projectId ?? 'all'] as const,
} as const;

// --- Hooks ---

export function useTeamCapacity(referenceDate: string) {
  return useQuery({
    queryKey: dashboardKeys.capacity(referenceDate),
    queryFn: () =>
      apiFetch<TeamCapacitySnapshotDto>(
        `/api/dashboard/capacity?date=${encodeURIComponent(referenceDate)}`
      ),
    staleTime: 5 * 60 * 1000,
    gcTime: 10 * 60 * 1000,
  });
}

export function useOverloadAnalysis(referenceDate: string) {
  return useQuery({
    queryKey: dashboardKeys.overload(referenceDate),
    queryFn: () =>
      apiFetch<OverloadAnalysisDto>(
        `/api/dashboard/overload?date=${encodeURIComponent(referenceDate)}`
      ),
    staleTime: 2 * 60 * 1000,
    gcTime: 5 * 60 * 1000,
  });
}

export function useSkillGapWarnings(
  referenceDate: string,
  projectId?: string
) {
  const params = new URLSearchParams({ date: referenceDate });
  if (projectId) {
    params.set('projectId', projectId);
  }

  return useQuery({
    queryKey: dashboardKeys.skillGaps(referenceDate, projectId),
    queryFn: () =>
      apiFetch<SkillGapWarningListDto>(
        `/api/dashboard/skill-gaps?${params.toString()}`
      ),
    staleTime: 3 * 60 * 1000,
    gcTime: 10 * 60 * 1000,
  });
}
