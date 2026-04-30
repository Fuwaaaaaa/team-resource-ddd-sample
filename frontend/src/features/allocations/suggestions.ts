import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/lib/http';

export interface AllocationCandidateScoreBreakdown {
  capacity: number;
  proficiency: number;
  experience: number;
}

export interface RecentAssignmentDto {
  projectId: string;
  projectName: string;
  allocationPercentage: number;
  periodStart: string;
  periodEnd: string;
  status: 'active' | 'revoked';
}

export interface AllocationCandidate {
  memberId: string;
  memberName: string;
  skillId: string;
  proficiency: number;
  availablePercentage: number;
  pastProjectExperienceCount: number;
  score: number;
  scoreBreakdown: AllocationCandidateScoreBreakdown;
  nextWeekConflict: boolean;
  reasons: string[];
  recentAssignments: RecentAssignmentDto[];
}

export type AllocationSuggestionsHint =
  | 'no_members_with_skill'
  | 'min_proficiency_too_high'
  | 'all_members_at_capacity';

export interface AllocationSuggestionsResponse {
  data: AllocationCandidate[];
  hint: AllocationSuggestionsHint | null;
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
      if (!input) return { data: [] as AllocationCandidate[], hint: null };
      const params = new URLSearchParams({
        projectId: input.projectId,
        skillId: input.skillId,
        minimumProficiency: String(input.minimumProficiency),
        periodStart: input.periodStart,
      });
      if (input.limit) params.set('limit', String(input.limit));
      return await apiFetch<AllocationSuggestionsResponse>(
        `/api/allocations/suggestions?${params.toString()}`,
      );
    },
    enabled: Boolean(input?.projectId && input?.skillId && input?.periodStart),
    staleTime: 30 * 1000,
  });
}
