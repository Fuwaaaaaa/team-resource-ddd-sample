import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiFetch } from '@/lib/http';

export interface NotificationDto {
  id: string;
  type: string;
  title: string;
  body: string;
  payload: Record<string, unknown> | null;
  read_at: string | null;
  created_at: string;
}

interface NotificationsResponse {
  data: NotificationDto[];
  meta: { unreadCount: number };
}

export const notificationKeys = {
  all: ['notifications'] as const,
  list: (unread: boolean) => [...notificationKeys.all, unread] as const,
};

export function useNotifications(unread = false) {
  return useQuery({
    queryKey: notificationKeys.list(unread),
    queryFn: async () =>
      apiFetch<NotificationsResponse>(
        `/api/notifications${unread ? '?unread=1' : ''}`,
      ),
    staleTime: 30 * 1000,
    refetchInterval: 60 * 1000, // 1 分ごとにポーリング
  });
}

export function useMarkNotificationRead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => {
      await apiFetch<{ data: NotificationDto }>(
        `/api/notifications/${id}/read`,
        { method: 'POST' },
      );
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: notificationKeys.all });
    },
  });
}

export function useMarkAllNotificationsRead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async () => {
      await apiFetch<{ ok: boolean }>('/api/notifications/read-all', {
        method: 'POST',
      });
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: notificationKeys.all });
    },
  });
}
