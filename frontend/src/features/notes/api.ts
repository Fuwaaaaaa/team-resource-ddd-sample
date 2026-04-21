import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiFetch } from '@/lib/http';

export type NoteEntityType = 'member' | 'project' | 'allocation';

export interface NoteDto {
  id: string;
  entity_type: NoteEntityType;
  entity_id: string;
  author_id: number | null;
  author: { id: number; name: string } | null;
  body: string;
  created_at: string;
  updated_at: string;
}

export const noteKeys = {
  byEntity: (type: NoteEntityType, id: string) => ['notes', type, id] as const,
};

export function useNotes(entityType: NoteEntityType | null, entityId: string | null) {
  return useQuery({
    queryKey: noteKeys.byEntity(entityType ?? 'member', entityId ?? ''),
    queryFn: async () => {
      const res = await apiFetch<{ data: NoteDto[] }>(
        `/api/notes?entityType=${entityType}&entityId=${entityId}`,
      );
      return res.data;
    },
    enabled: Boolean(entityType && entityId),
    staleTime: 30 * 1000,
  });
}

export function useCreateNote() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: { entityType: NoteEntityType; entityId: string; body: string }) => {
      const res = await apiFetch<{ data: NoteDto }>('/api/notes', {
        method: 'POST',
        body: JSON.stringify(input),
      });
      return res.data;
    },
    onSuccess: (_n, input) => {
      qc.invalidateQueries({ queryKey: noteKeys.byEntity(input.entityType, input.entityId) });
    },
  });
}

export function useDeleteNote() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: { id: string; entityType: NoteEntityType; entityId: string }) => {
      await apiFetch<void>(`/api/notes/${input.id}`, { method: 'DELETE' });
      return input;
    },
    onSuccess: (input) => {
      qc.invalidateQueries({ queryKey: noteKeys.byEntity(input.entityType, input.entityId) });
    },
  });
}
