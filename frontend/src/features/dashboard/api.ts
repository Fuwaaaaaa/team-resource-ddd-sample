import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/lib/http';
import type {
  CapacityForecastDto,
  KpiSummaryDto,
  KpiTrendDto,
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
  kpiSummary: (date: string) =>
    [...dashboardKeys.all, 'kpiSummary', date] as const,
  capacityForecast: (date: string, months: number) =>
    [...dashboardKeys.all, 'capacityForecast', date, months] as const,
  kpiTrend: (date: string, days: number) =>
    [...dashboardKeys.all, 'kpiTrend', date, days] as const,
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

export function useKpiSummary(referenceDate: string) {
  return useQuery({
    queryKey: dashboardKeys.kpiSummary(referenceDate),
    queryFn: async () => {
      const res = await apiFetch<{ data: KpiSummaryDto }>(
        `/api/dashboard/kpi-summary?date=${encodeURIComponent(referenceDate)}`,
      );
      return res.data;
    },
    staleTime: 60 * 1000,
    gcTime: 5 * 60 * 1000,
  });
}

export function useCapacityForecast(referenceDate: string, monthsAhead: number) {
  return useQuery({
    queryKey: dashboardKeys.capacityForecast(referenceDate, monthsAhead),
    queryFn: async () => {
      const res = await apiFetch<{ data: CapacityForecastDto }>(
        `/api/dashboard/capacity-forecast?date=${encodeURIComponent(referenceDate)}&months=${monthsAhead}`,
      );
      return res.data;
    },
    staleTime: 60 * 1000,
    gcTime: 5 * 60 * 1000,
  });
}

export function useKpiTrend(referenceDate: string, days: number) {
  return useQuery({
    queryKey: dashboardKeys.kpiTrend(referenceDate, days),
    queryFn: async () => {
      const res = await apiFetch<{ data: KpiTrendDto }>(
        `/api/dashboard/kpi-trend?date=${encodeURIComponent(referenceDate)}&days=${days}`,
      );
      return res.data;
    },
    staleTime: 5 * 60 * 1000,
    gcTime: 10 * 60 * 1000,
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
