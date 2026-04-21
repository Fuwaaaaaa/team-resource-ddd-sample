import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiFetch, HttpError } from '@/lib/http';

export type UserRole = 'admin' | 'manager' | 'viewer';

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  role: UserRole;
}

const authKeys = {
  me: ['auth', 'me'] as const,
};

async function fetchMe(): Promise<AuthUser | null> {
  try {
    return await apiFetch<AuthUser>('/api/me');
  } catch (e) {
    if (e instanceof HttpError && e.status === 401) return null;
    throw e;
  }
}

export function useMe() {
  return useQuery({
    queryKey: authKeys.me,
    queryFn: fetchMe,
    staleTime: 60 * 1000,
    retry: false,
  });
}

/** Convenience hook that returns the current user's role permissions. */
export function usePermissions() {
  const { data: me } = useMe();
  const role: UserRole | undefined = me?.role;
  return {
    role,
    canWrite: role === 'admin' || role === 'manager',
    canViewAuditLog: role === 'admin',
  };
}

export interface LoginInput {
  email: string;
  password: string;
}

export function useLogin() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: LoginInput): Promise<AuthUser> => {
      return await apiFetch<AuthUser>('/api/login', {
        method: 'POST',
        body: JSON.stringify(input),
      });
    },
    onSuccess: (user) => {
      queryClient.setQueryData(authKeys.me, user);
    },
  });
}

export function useLogout() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (): Promise<void> => {
      await apiFetch<void>('/api/logout', { method: 'POST' });
    },
    onSuccess: () => {
      queryClient.setQueryData(authKeys.me, null);
      queryClient.clear();
    },
  });
}
