import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/lib/http';
import type { SkillDto } from '@/features/dashboard/types';

export const skillKeys = {
  all: ['skills'] as const,
};

export function useSkills() {
  return useQuery({
    queryKey: skillKeys.all,
    queryFn: async () => {
      const res = await apiFetch<{ data: SkillDto[] }>('/api/skills');
      return res.data;
    },
    staleTime: 10 * 60 * 1000,
  });
}
