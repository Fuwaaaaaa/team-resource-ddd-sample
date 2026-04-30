import { useMutation, useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/lib/http';

export interface InvitePreviewDto {
  name: string;
  email: string;
  role: 'admin' | 'manager' | 'viewer';
}

export interface AcceptInviteInput {
  password: string;
  password_confirmation: string;
}

export interface AcceptInviteResponse {
  status: 'ok';
  email: string;
}

export const inviteKeys = {
  preview: (token: string) => ['invite', 'preview', token] as const,
};

/**
 * 招待 token に対応する user 情報を取得 (公開 API、 認証不要)。
 * 404 のとき token は無効 / 失効 / 既に accept 済。
 */
export function useInvitePreview(token: string) {
  return useQuery({
    queryKey: inviteKeys.preview(token),
    queryFn: async () => await apiFetch<InvitePreviewDto>(`/api/invite/${token}`),
    retry: false, // 404 は再試行しない
    enabled: Boolean(token) && /^[0-9a-f]{64}$/.test(token),
    staleTime: Infinity,
  });
}

export function useAcceptInvite(token: string) {
  return useMutation({
    mutationFn: async (input: AcceptInviteInput): Promise<AcceptInviteResponse> =>
      await apiFetch<AcceptInviteResponse>(`/api/invite/${token}/accept`, {
        method: 'POST',
        body: JSON.stringify(input),
      }),
  });
}
