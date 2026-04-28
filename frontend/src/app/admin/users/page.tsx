'use client';

import { useState } from 'react';
import { AppHeader } from '@/components/layout/AppHeader';
import { Forbidden } from '@/components/atoms/Forbidden';
import { RoleBadge } from '@/components/atoms/RoleBadge';
import { UserCreateModal } from '@/components/molecules/UserCreateModal';
import { UserRoleChangeModal } from '@/components/molecules/UserRoleChangeModal';
import { UserResetPasswordConfirm } from '@/components/molecules/UserResetPasswordConfirm';
import { useTranslation } from '@/lib/i18n/useTranslation';
import { useAdminUsers } from '@/features/admin/users/api';
import { useMe, usePermissions } from '@/features/auth/api';
import type { AdminUserDto } from '@/features/admin/users/types';

function formatRelative(iso: string, locale: string): string {
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  const diffMs = Date.now() - d.getTime();
  const diffSec = Math.round(diffMs / 1000);
  const rtf = new Intl.RelativeTimeFormat(locale === 'ja' ? 'ja' : 'en', { numeric: 'auto' });
  const abs = Math.abs(diffSec);
  if (abs < 60) return rtf.format(-diffSec, 'second');
  if (abs < 3600) return rtf.format(-Math.round(diffSec / 60), 'minute');
  if (abs < 86_400) return rtf.format(-Math.round(diffSec / 3600), 'hour');
  if (abs < 86_400 * 30) return rtf.format(-Math.round(diffSec / 86_400), 'day');
  if (abs < 86_400 * 365) return rtf.format(-Math.round(diffSec / (86_400 * 30)), 'month');
  return rtf.format(-Math.round(diffSec / (86_400 * 365)), 'year');
}

export default function AdminUsersPage() {
  const t = useTranslation();
  const me = useMe();
  const permissions = usePermissions();
  const [search, setSearch] = useState('');
  const [createOpen, setCreateOpen] = useState(false);
  const [editTarget, setEditTarget] = useState<AdminUserDto | null>(null);
  const [resetTarget, setResetTarget] = useState<AdminUserDto | null>(null);

  const users = useAdminUsers({ search: search || undefined, perPage: 50 });

  // Auth gate (UI side; server also enforces 403 via role:admin middleware)
  if (me.isLoading) {
    return (
      <>
        <AppHeader />
        <div className="max-w-[1400px] mx-auto px-4 py-8 text-sm text-fg-muted">
          {t('common.loading')}
        </div>
      </>
    );
  }
  if (!permissions.isAdmin) {
    return (
      <>
        <AppHeader />
        <Forbidden />
      </>
    );
  }

  const total = users.data?.meta.total ?? 0;
  const count = users.data?.data.length ?? 0;

  return (
    <>
      <AppHeader />
      <UserCreateModal open={createOpen} onClose={() => setCreateOpen(false)} />
      <UserRoleChangeModal user={editTarget} onClose={() => setEditTarget(null)} />
      <UserResetPasswordConfirm
        user={resetTarget}
        isSelf={!!resetTarget && resetTarget.id === me.data?.id}
        onClose={() => setResetTarget(null)}
      />

      <div className="max-w-[1400px] mx-auto px-4 py-8 space-y-4">
        {/* Page header */}
        <div className="flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-3">
          <h1 className="text-2xl font-bold text-fg">{t('admin.users.title')}</h1>
          <div className="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3">
            {users.data && (
              <span className="text-xs text-fg-muted">
                {t('admin.users.showingCount')
                  .replace('{count}', String(count))
                  .replace('{total}', String(total))}
              </span>
            )}
            <button
              type="button"
              onClick={() => setCreateOpen(true)}
              className="px-3 py-1.5 text-sm font-medium rounded-md bg-primary text-white hover:bg-primary-hover"
            >
              {t('admin.users.create')}
            </button>
          </div>
        </div>

        {/* Search */}
        <div className="p-4 bg-surface rounded-lg border border-border">
          <label className="block">
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder={t('admin.users.search.placeholder')}
              className="w-full sm:w-80 px-3 py-1.5 text-sm border border-border rounded-md bg-surface text-fg"
            />
          </label>
        </div>

        {/* Error / Empty / Table */}
        {users.isError && (
          <div
            role="alert"
            className="text-sm bg-danger-bg text-danger border border-danger/40 rounded px-3 py-2 flex items-center justify-between"
          >
            <span>
              <strong>{t('common.error.prefix')}</strong> {t('admin.users.loadError')}
            </span>
            <button
              type="button"
              onClick={() => users.refetch()}
              className="px-3 py-1 text-xs rounded-md bg-danger text-white hover:opacity-90"
            >
              {t('admin.users.retry')}
            </button>
          </div>
        )}

        <div className="bg-surface rounded-lg border border-border overflow-x-auto">
          <table className="w-full text-sm min-w-[640px]">
            <thead className="bg-surface-muted text-fg-muted">
              <tr>
                <th className="px-4 py-2 text-left font-medium">Name</th>
                <th className="px-4 py-2 text-left font-medium">Email</th>
                <th className="px-4 py-2 text-left font-medium">{t('admin.users.role.label')}</th>
                <th className="px-4 py-2 text-left font-medium">Created</th>
                <th className="px-4 py-2 text-left font-medium" aria-label="actions"></th>
              </tr>
            </thead>
            <tbody>
              {users.isLoading && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-fg-muted">
                    {t('common.loading')}
                  </td>
                </tr>
              )}
              {users.data?.data.length === 0 && !users.isLoading && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-fg-muted">
                    {search
                      ? t('admin.users.emptyAfterSearch').replace('{search}', search)
                      : t('admin.users.empty')}
                    {search && (
                      <button
                        type="button"
                        onClick={() => setSearch('')}
                        className="ml-2 text-primary hover:underline"
                      >
                        {t('admin.users.searchClear')}
                      </button>
                    )}
                  </td>
                </tr>
              )}
              {users.data?.data.map((u) => (
                <tr key={u.id} className="border-t border-border hover:bg-surface-muted">
                  <td className="px-4 py-2 text-fg whitespace-nowrap">{u.name}</td>
                  <td className="px-4 py-2 text-fg-muted whitespace-nowrap">{u.email}</td>
                  <td className="px-4 py-2">
                    <RoleBadge role={u.role} />
                  </td>
                  <td className="px-4 py-2 text-xs text-fg-muted whitespace-nowrap" title={u.createdAt}>
                    {formatRelative(u.createdAt, 'ja')}
                  </td>
                  <td className="px-4 py-2 whitespace-nowrap">
                    <div className="flex gap-2">
                      <button
                        type="button"
                        onClick={() => setEditTarget(u)}
                        className="px-3 py-1 text-xs rounded-md bg-surface-muted text-fg hover:bg-border min-h-[40px]"
                      >
                        {t('admin.users.actions.edit')}
                      </button>
                      <button
                        type="button"
                        onClick={() => setResetTarget(u)}
                        className="px-3 py-1 text-xs rounded-md bg-surface-muted text-fg hover:bg-border min-h-[40px]"
                      >
                        {t('admin.users.actions.reset')}
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </>
  );
}
