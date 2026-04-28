'use client';

import { useEffect, useRef, useState } from 'react';
import { HttpError } from '@/lib/http';
import { useTranslation } from '@/lib/i18n/useTranslation';
import { useChangeUserRole } from '@/features/admin/users/api';
import { RoleBadge } from '@/components/atoms/RoleBadge';
import type { AdminUserDto } from '@/features/admin/users/types';
import type { UserRole } from '@/features/auth/api';

export interface UserRoleChangeModalProps {
  user: AdminUserDto | null;
  onClose: () => void;
}

const REASON_MAX = 200;

export function UserRoleChangeModal({ user, onClose }: UserRoleChangeModalProps) {
  const t = useTranslation();
  const change = useChangeUserRole();
  const [newRole, setNewRole] = useState<UserRole>('viewer');
  const [reason, setReason] = useState('');
  const [serverError, setServerError] = useState<string | null>(null);
  const reasonRef = useRef<HTMLTextAreaElement>(null);

  useEffect(() => {
    if (user) {
      // initialize new role to first non-current option
      const first = (['admin', 'manager', 'viewer'] as UserRole[]).find((r) => r !== user.role);
      setNewRole(first ?? 'viewer');
      setReason('');
      setServerError(null);
      setTimeout(() => reasonRef.current?.focus(), 0);
    }
  }, [user]);

  useEffect(() => {
    if (!user) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [user, onClose]);

  if (!user) return null;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setServerError(null);
    try {
      await change.mutateAsync({
        id: user.id,
        input: {
          role: newRole,
          reason,
          // server normalizes via DateTimeImmutable; ISO 8601 from updatedAt is fine
          expectedUpdatedAt: user.updatedAt,
        },
      });
      onClose();
    } catch (err) {
      if (err instanceof HttpError) {
        const body = err.body as { error?: string };
        switch (body.error) {
          case 'cannot_change_self':
            setServerError(t('admin.users.errors.cannotChangeSelf'));
            break;
          case 'last_admin_lock':
            setServerError(t('admin.users.errors.lastAdminLock'));
            break;
          case 'occ_mismatch':
            setServerError(t('admin.users.errors.occMismatch'));
            break;
          default:
            setServerError(t('admin.users.errors.generic'));
        }
      } else {
        setServerError(t('admin.users.errors.generic'));
      }
    }
  };

  const reasonValid = reason.trim().length > 0 && reason.length <= REASON_MAX;
  const counterId = `role-change-reason-counter-${user.id}`;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="user-role-change-modal-title"
      onClick={onClose}
    >
      <div
        className="relative w-full sm:max-w-md max-h-[90vh] overflow-y-auto bg-surface text-fg rounded-lg border border-border shadow-lg"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="sticky top-0 bg-surface border-b border-border px-6 py-4 flex items-center justify-between">
          <h2 id="user-role-change-modal-title" className="text-lg font-bold">
            {t('admin.users.role.modal.title')}
          </h2>
          <button
            type="button"
            onClick={onClose}
            aria-label={t('admin.users.create.modal.close')}
            className="text-fg-muted hover:text-fg text-2xl leading-none px-2"
          >
            ×
          </button>
        </div>

        <form onSubmit={handleSubmit} className="px-6 py-4 space-y-4">
          {serverError && (
            <div
              role="alert"
              className="text-sm bg-danger-bg text-danger border border-danger/40 rounded px-3 py-2"
            >
              <strong>{t('common.error.prefix')}</strong> {serverError}
            </div>
          )}

          <div>
            <p className="text-sm text-fg">{user.name}</p>
            <p className="text-xs text-fg-muted">{user.email}</p>
          </div>

          <div>
            <span className="block text-xs font-medium text-fg mb-1">
              {t('admin.users.role.modal.currentRole')}
            </span>
            <RoleBadge role={user.role} />
          </div>

          <fieldset>
            <legend className="block text-xs font-medium text-fg mb-2">
              {t('admin.users.role.modal.newRole')} *
            </legend>
            <div className="flex flex-col gap-2">
              {(['admin', 'manager', 'viewer'] as UserRole[]).map((r) => {
                const isCurrent = r === user.role;
                return (
                  <label
                    key={r}
                    className={`inline-flex items-center gap-2 text-sm ${isCurrent ? 'opacity-50' : ''}`}
                  >
                    <input
                      type="radio"
                      name="new-role"
                      value={r}
                      checked={newRole === r}
                      onChange={() => setNewRole(r)}
                      disabled={isCurrent}
                    />
                    <span>{r}</span>
                  </label>
                );
              })}
            </div>
          </fieldset>

          <label className="block">
            <span className="block text-xs font-medium text-fg mb-1">
              {t('admin.users.role.modal.reason')} *
            </span>
            <textarea
              ref={reasonRef}
              value={reason}
              onChange={(e) => setReason(e.target.value.slice(0, REASON_MAX))}
              required
              aria-required="true"
              aria-describedby={counterId}
              placeholder={t('admin.users.role.modal.reasonPlaceholder')}
              rows={3}
              className="w-full px-3 py-2 text-sm border border-border rounded-md bg-surface text-fg"
            />
            <span id={counterId} className="block text-xs text-fg-muted mt-1">
              {reason.length} / {REASON_MAX}
            </span>
          </label>

          <div className="flex justify-end gap-2 pt-2 border-t border-border">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-sm text-fg-muted hover:text-fg"
            >
              {t('common.cancel')}
            </button>
            <button
              type="submit"
              disabled={change.isPending || !reasonValid || newRole === user.role}
              className="px-4 py-2 text-sm font-medium rounded-md bg-primary text-white hover:bg-primary-hover disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {change.isPending
                ? t('admin.users.role.modal.submitting')
                : t('admin.users.role.modal.submit')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
