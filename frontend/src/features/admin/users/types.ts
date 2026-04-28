import type { UserRole } from '@/features/auth/api';

export interface AdminUserDto {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  createdAt: string; // ISO 8601
  updatedAt: string; // ISO 8601
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
  generatedPassword: string;
}

export interface ChangeUserRoleInput {
  role: UserRole;
  reason: string;
  expectedUpdatedAt: string;
}

export interface PasswordResetResponse {
  user: AdminUserDto;
  generatedPassword: string;
  requiresRelogin: boolean;
}
