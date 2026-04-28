import { renderHook } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { ReactNode } from 'react';
import { authKeys, usePermissions, type AuthUser } from '../api';

function wrapper({ data }: { data: AuthUser | null }): ({ children }: { children: ReactNode }) => JSX.Element {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  qc.setQueryData(authKeys.me, data);
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={qc}>{children}</QueryClientProvider>
  );
}

const ADMIN: AuthUser = { id: 1, name: 'A', email: 'a@x.com', role: 'admin' };
const MANAGER: AuthUser = { id: 2, name: 'M', email: 'm@x.com', role: 'manager' };
const VIEWER: AuthUser = { id: 3, name: 'V', email: 'v@x.com', role: 'viewer' };

describe('usePermissions', () => {
  it('admin: canWrite + canViewAuditLog + isAdmin all true', () => {
    const { result } = renderHook(() => usePermissions(), { wrapper: wrapper({ data: ADMIN }) });
    expect(result.current.canWrite).toBe(true);
    expect(result.current.canViewAuditLog).toBe(true);
    expect(result.current.isAdmin).toBe(true);
  });

  it('manager: canWrite=true, isAdmin=false, canViewAuditLog=false', () => {
    const { result } = renderHook(() => usePermissions(), { wrapper: wrapper({ data: MANAGER }) });
    expect(result.current.canWrite).toBe(true);
    expect(result.current.canViewAuditLog).toBe(false);
    expect(result.current.isAdmin).toBe(false);
  });

  it('viewer: all false', () => {
    const { result } = renderHook(() => usePermissions(), { wrapper: wrapper({ data: VIEWER }) });
    expect(result.current.canWrite).toBe(false);
    expect(result.current.canViewAuditLog).toBe(false);
    expect(result.current.isAdmin).toBe(false);
  });

  it('unauthenticated (data=null): all false', () => {
    const { result } = renderHook(() => usePermissions(), { wrapper: wrapper({ data: null }) });
    expect(result.current.canWrite).toBe(false);
    expect(result.current.canViewAuditLog).toBe(false);
    expect(result.current.isAdmin).toBe(false);
  });
});
