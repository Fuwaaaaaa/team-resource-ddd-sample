import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/lib/http';

export interface AllocationCandidate {
  memberId: string;
  memberName: string;
  skillId: string;
  proficiency: number;
  availablePercentage: number;
  pastProjectExperienceCount: number;
  score: number;
  reasons: string[];
}

export interface SuggestionsInput {
  projectId: string;
  skillId: string;
  minimumProficiency: number;
  periodStart: string;
  limit?: number;
}

export function useAllocationSuggestions(input: SuggestionsInput | null) {
  return useQuery({
    queryKey: ['allocation-suggestions', input] as const,
    queryFn: async () => {
      if (!input) return [];
      const params = new URLSearchParams({
        projectId: input.projectId,
        skillId: input.skillId,
        minimumProficiency: String(input.minimumProficiency),
        periodStart: input.periodStart,
      });
      if (input.limit) params.set('limit', String(input.limit));
      const res = await apiFetch<{ data: AllocationCandidate[] }>(
        `/api/allocations/suggestions?${params.toString()}`,
      );
      return res.data;
    },
    enabled: Boolean(input?.projectId && input?.skillId && input?.periodStart),
    staleTime: 30 * 1000,
  });
}
