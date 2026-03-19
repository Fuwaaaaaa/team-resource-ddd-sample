import { useQuery } from '@tanstack/react-query';
import type {
  TeamCapacitySnapshotDto,
  OverloadAnalysisDto,
  SkillGapWarningListDto,
} from './types';

const API_BASE = process.env.NEXT_PUBLIC_API_BASE_URL ?? '/api';

async function fetchJson<T>(url: string): Promise<T> {
  const response = await fetch(url);
  if (!response.ok) {
    throw new Error(`API error: ${response.status} ${response.statusText}`);
  }
  return response.json() as Promise<T>;
}

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
      fetchJson<TeamCapacitySnapshotDto>(
        `${API_BASE}/dashboard/capacity?date=${encodeURIComponent(referenceDate)}`
      ),
    staleTime: 5 * 60 * 1000,
    gcTime: 10 * 60 * 1000,
  });
}

export function useOverloadAnalysis(referenceDate: string) {
  return useQuery({
    queryKey: dashboardKeys.overload(referenceDate),
    queryFn: () =>
      fetchJson<OverloadAnalysisDto>(
        `${API_BASE}/dashboard/overload?date=${encodeURIComponent(referenceDate)}`
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
      fetchJson<SkillGapWarningListDto>(
        `${API_BASE}/dashboard/skill-gaps?${params.toString()}`
      ),
    staleTime: 3 * 60 * 1000,
    gcTime: 10 * 60 * 1000,
  });
}
