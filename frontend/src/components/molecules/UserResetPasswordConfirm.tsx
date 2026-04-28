'use client';

import { useEffect, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { HttpError } from '@/lib/http';
import { useTranslation } from '@/lib/i18n/useTranslation';
import { useResetUserPassword } from '@/features/admin/users/api';
import { useLogout } from '@/features/auth/api';
import type { AdminUserDto } from '@/features/admin/users/types';

export interface UserResetPasswordConfirmProps {
  user: AdminUserDto | null;
  isSelf: boolean;
  onClose: () => void;
}

const RELOGIN_COUNTDOWN_SECONDS = 5;

export function UserResetPasswordConfirm({
  user,
  isSelf,
  onClose,
}: UserResetPasswordConfirmProps) {
  const t = useTranslation();
  const reset = useResetUserPassword();
  const router = useRouter();
  const logout = useLogout();
  const [serverError, setServerError] = useState<string | null>(null);
  const [generatedPassword, setGeneratedPassword] = useState<string | null>(null);
  const [requiresRelogin, setRequiresRelogin] = useState(false);
  const [countdown, setCountdown] = useState(RELOGIN_COUNTDOWN_SECONDS);
  const [copied, setCopied] = useState(false);
  const cancelRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (user) {
      setServerError(null);
      setGeneratedPassword(null);
      setRequiresRelogin(false);
      setCountdown(RELOGIN_COUNTDOWN_SECONDS);
      setCopied(false);
      setTimeout(() => cancelRef.current?.focus(), 0);
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

  // Self-reset countdown → automatic logout + redirect.
  // The server has already deleted this user's session as part of the
  // reset, so POST /api/logout will return 401. We swallow that error
  // intentionally — the redirect to /login is what matters.
  useEffect(() => {
    if (!requiresRelogin) return;
    if (countdown <= 0) {
      void (async () => {
        try {
          await logout.mutateAsync();
        } catch {
          // expected: session was already invalidated server-side
        }
        router.push('/login');
      })();
      return;
    }
    const id = setTimeout(() => setCountdown((c) => c - 1), 1000);
    return () => clearTimeout(id);
  }, [requiresRelogin, countdown, logout, router]);

  if (!user) return null;

  const handleSubmit = async () => {
    setServerError(null);
    try {
      const result = await reset.mutateAsync(user.id);
      setGeneratedPassword(result.generatedPassword);
      setRequiresRelogin(result.requiresRelogin);
    } catch (err) {
      if (err instanceof HttpError) {
        setServerError(t('admin.users.errors.generic'));
      } else {
        setServerError(t('admin.users.errors.generic'));
      }
    }
  };

  const handleCopy = async () => {
    if (!generatedPassword) return;
    try {
      await navigator.clipboard.writeText(generatedPassword);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // ignore
    }
  };

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="user-reset-modal-title"
      onClick={onClose}
    >
      <div
        className="relative w-full sm:max-w-md max-h-[90vh] overflow-y-auto bg-surface text-fg rounded-lg border border-border shadow-lg"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="sticky top-0 bg-surface border-b border-border px-6 py-4 flex items-center justify-between">
          <h2 id="user-reset-modal-title" className="text-lg font-bold">
            {t('admin.users.reset.modal.title')}
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

        {/* Stage 1: confirm */}
        {!generatedPassword && (
          <div className="px-6 py-4 space-y-4">
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
            <div className="text-sm bg-warning/10 text-warning border border-warning/40 rounded px-3 py-2 space-y-1">
              <p className="font-bold">{t('admin.users.reset.modal.warning')}</p>
              <ul className="list-disc list-inside text-fg space-y-0.5">
                <li>{t('admin.users.reset.modal.warning1')}</li>
                <li>{t('admin.users.reset.modal.warning2')}</li>
                <li>{t('admin.users.reset.modal.warning3')}</li>
              </ul>
              {isSelf && (
                <p className="text-fg-muted pt-2">{t('admin.users.reset.modal.selfNotice')}</p>
              )}
            </div>
            <div className="flex justify-end gap-2 pt-2 border-t border-border">
              <button
                type="button"
                ref={cancelRef}
                onClick={onClose}
                className="px-4 py-2 text-sm text-fg-muted hover:text-fg"
              >
                {t('common.cancel')}
              </button>
              <button
                type="button"
                onClick={handleSubmit}
                disabled={reset.isPending}
                className="px-4 py-2 text-sm font-medium rounded-md bg-primary text-white hover:bg-primary-hover disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {reset.isPending
                  ? t('admin.users.reset.modal.submitting')
                  : t('admin.users.reset.modal.submit')}
              </button>
            </div>
          </div>
        )}

        {/* Stage 2: success + new password */}
        {generatedPassword && (
          <div className="px-6 py-4 space-y-4">
            <div>
              <span className="block text-xs font-medium text-fg mb-1">
                {t('admin.users.create.modal.passwordLabel')}
              </span>
              <div className="flex items-center gap-2">
                <code
                  aria-label={t('admin.users.create.modal.passwordLabel')}
                  className="flex-1 font-mono text-base bg-surface-muted text-fg px-3 py-2 rounded border border-border break-all"
                >
                  {generatedPassword}
                </code>
                <button
                  type="button"
                  onClick={handleCopy}
                  className="px-3 py-2 text-xs rounded-md bg-surface-muted text-fg hover:bg-border"
                >
                  {copied
                    ? t('admin.users.create.modal.copied')
                    : t('admin.users.create.modal.copy')}
                </button>
              </div>
            </div>
            <div
              role="status"
              className="text-xs bg-warning/10 text-warning border border-warning/40 rounded px-3 py-2"
            >
              <strong>{t('common.warn.prefix')}</strong>{' '}
              {t('admin.users.create.modal.passwordWarning')}
            </div>
            {requiresRelogin && (
              <div
                role="status"
                aria-live="polite"
                className="text-xs bg-primary/10 text-primary border border-primary/40 rounded px-3 py-2"
              >
                {t('admin.users.reset.relogin.notice').replace('{seconds}', String(countdown))}
              </div>
            )}
            <div className="flex justify-end pt-2 border-t border-border">
              <button
                type="button"
                onClick={onClose}
                className="px-4 py-2 text-sm font-medium rounded-md bg-primary text-white hover:bg-primary-hover"
              >
                {t('admin.users.create.modal.close')}
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
