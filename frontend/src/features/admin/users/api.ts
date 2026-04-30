import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiFetch } from '@/lib/http';
import { authKeys } from '@/features/auth/api';
import type {
  AdminUserDto,
  AdminUserFilters,
  AdminUserListResponse,
  ChangeUserRoleInput,
  CreatedUserResponse,
  CreateUserInput,
  PasswordResetResponse,
} from './types';

export const adminUserKeys = {
  all: ['admin', 'users'] as const,
  list: (filters: AdminUserFilters) => [...adminUserKeys.all, 'list', filters] as const,
};

export function useAdminUsers(filters: AdminUserFilters = {}) {
  const params = new URLSearchParams();
  if (filters.search) params.set('search', filters.search);
  if (filters.perPage) params.set('perPage', String(filters.perPage));
  const qs = params.toString();

  return useQuery({
    queryKey: adminUserKeys.list(filters),
    queryFn: async () =>
      await apiFetch<AdminUserListResponse>(`/api/admin/users${qs ? `?${qs}` : ''}`),
    staleTime: 30 * 1000,
  });
}

export function useCreateUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateUserInput): Promise<CreatedUserResponse> =>
      await apiFetch<CreatedUserResponse>('/api/admin/users', {
        method: 'POST',
        body: JSON.stringify(input),
      }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: adminUserKeys.all });
    },
  });
}

export function useChangeUserRole() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (args: { id: number; input: ChangeUserRoleInput }): Promise<{ user: AdminUserDto }> =>
      await apiFetch<{ user: AdminUserDto }>(`/api/admin/users/${args.id}/role`, {
        method: 'PATCH',
        body: JSON.stringify(args.input),
      }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: adminUserKeys.all });
      // role might be the *current* user's — keep /me in sync
      void qc.invalidateQueries({ queryKey: authKeys.me });
    },
  });
}

export function useResetUserPassword() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: number): Promise<PasswordResetResponse> =>
      await apiFetch<PasswordResetResponse>(`/api/admin/users/${id}/reset-password`, {
        method: 'POST',
      }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: adminUserKeys.all });
    },
  });
}

export function useDisableUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: number): Promise<{ user: AdminUserDto }> =>
      await apiFetch<{ user: AdminUserDto }>(`/api/admin/users/${id}/disable`, {
        method: 'POST',
      }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: adminUserKeys.all });
    },
  });
}

export function useEnableUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: number): Promise<{ user: AdminUserDto }> =>
      await apiFetch<{ user: AdminUserDto }>(`/api/admin/users/${id}/enable`, {
        method: 'POST',
      }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: adminUserKeys.all });
    },
  });
}
