import type { UserRole } from '@/features/auth/api';

export interface AdminUserDto {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  createdAt: string; // ISO 8601
  updatedAt: string; // ISO 8601
  disabledAt: string | null; // ISO 8601 when disabled, null otherwise
}

export interface AdminUserListResponse {
  data: AdminUserDto[];
  meta: {
    total: number;
    page: number;
    perPage: number;
    lastPage: number;
  };
}

export interface AdminUserFilters {
  search?: string;
  perPage?: number;
}

export interface CreateUserInput {
  name: string;
  email: string;
  role: UserRole;
}

export interface CreatedUserResponse {
  user: AdminUserDto;
  inviteSentTo: string;
  inviteExpiresAt: string; // ISO 8601
  inviteUrl: string;
}

export interface ChangeUserRoleInput {
  role: UserRole;
  reason: string;
  expectedUpdatedAt: string;
}

export interface PasswordResetResponse {
  user: AdminUserDto;
  inviteUrl: string;
  inviteExpiresAt: string; // ISO 8601
  requiresRelogin: boolean;
}
