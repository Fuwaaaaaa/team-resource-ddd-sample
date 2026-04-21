import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/lib/http';

export interface SkillHistoryEntry {
  skillId: string;
  proficiency: number;
  changedAt: string;
  changedBy: string | null;
  changedByName: string | null;
}

export function useMemberSkillHistory(memberId: string | null, skillId?: string) {
  const qs = skillId ? `?skillId=${encodeURIComponent(skillId)}` : '';
  return useQuery({
    queryKey: ['skill-history', memberId, skillId ?? null] as const,
    queryFn: async () => {
      const res = await apiFetch<{ data: SkillHistoryEntry[] }>(
        `/api/members/${memberId}/skill-history${qs}`,
      );
      return res.data;
    },
    enabled: !!memberId,
    staleTime: 30 * 1000,
  });
}
