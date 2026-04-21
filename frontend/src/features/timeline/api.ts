import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/lib/http';
import type { TimelineResponse } from './types';

export const timelineKeys = {
  all: ['timeline'] as const,
  window: (start: string, end: string) => [...timelineKeys.all, start, end] as const,
};

export function useTimeline(periodStart: string, periodEnd: string) {
  return useQuery({
    queryKey: timelineKeys.window(periodStart, periodEnd),
    queryFn: async () => {
      const params = new URLSearchParams({ periodStart, periodEnd });
      return await apiFetch<TimelineResponse>(`/api/timeline?${params.toString()}`);
    },
    staleTime: 60 * 1000,
  });
}
