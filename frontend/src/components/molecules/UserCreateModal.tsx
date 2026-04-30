'use client';

import { useEffect, useRef, useState } from 'react';
import { HttpError } from '@/lib/http';
import { useTranslation } from '@/lib/i18n/useTranslation';
import { useCreateUser } from '@/features/admin/users/api';
import type { UserRole } from '@/features/auth/api';

export interface UserCreateModalProps {
  open: boolean;
  onClose: () => void;
}

interface InviteResult {
  email: string;
  expiresAt: string; // ISO 8601
  url: string;
}

/**
 * Two-stage modal:
 *   stage 1 (form): name / email / role radio
 *   stage 2 (invite-sent): confirms the email recipient + shows the invite URL
 *                          for re-sharing if the email never arrives.
 *
 * Note: the admin never sees a password in this flow. The recipient sets their
 * own from the email link (see /invite/[token] page). The invite URL is shown
 * here only as a fallback for when the SMTP path is broken (dev environments,
 * spam folders) — in production the email is the canonical channel.
 */
export function UserCreateModal({ open, onClose }: UserCreateModalProps) {
  const t = useTranslation();
  const create = useCreateUser();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [role, setRole] = useState<UserRole>('viewer');
  const [serverError, setServerError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [invite, setInvite] = useState<InviteResult | null>(null);
  const [copied, setCopied] = useState(false);
  const firstInputRef = useRef<HTMLInputElement>(null);

  // Focus first field on open / reset state on close
  useEffect(() => {
    if (open) {
      setName('');
      setEmail('');
      setRole('viewer');
      setServerError(null);
      setFieldErrors({});
      setInvite(null);
      setCopied(false);
      setTimeout(() => firstInputRef.current?.focus(), 0);
    }
  }, [open]);

  // ESC to close
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [open, onClose]);

  if (!open) return null;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setServerError(null);
    setFieldErrors({});
    try {
      const result = await create.mutateAsync({ name, email, role });
      setInvite({
        email: result.inviteSentTo,
        expiresAt: result.inviteExpiresAt,
        url: result.inviteUrl,
      });
    } catch (err) {
      if (err instanceof HttpError) {
        if (err.status === 422) {
          const body = err.body as { errors?: Record<string, string[]>; error?: string };
          if (body.error === 'email_taken') {
            setFieldErrors({ email: [t('admin.users.errors.emailTaken')] });
          } else if (body.errors) {
            setFieldErrors(body.errors);
          } else {
            setServerError(t('admin.users.errors.generic'));
          }
        } else {
          setServerError(t('admin.users.errors.generic'));
        }
      } else {
        setServerError(t('admin.users.errors.generic'));
      }
    }
  };

  const handleCopy = async () => {
    if (!invite) return;
    try {
      await navigator.clipboard.writeText(invite.url);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // ignore: clipboard may be unavailable in insecure contexts
    }
  };

  const fmtExpires = (iso: string) => new Date(iso).toLocaleString('ja-JP');

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="user-create-modal-title"
      onClick={onClose}
    >
      <div
        className="relative w-full sm:max-w-md max-h-[90vh] overflow-y-auto bg-surface text-fg rounded-lg border border-border shadow-lg"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div className="sticky top-0 bg-surface border-b border-border px-6 py-4 flex items-center justify-between">
          <h2 id="user-create-modal-title" className="text-lg font-bold">
            {invite
              ? t('admin.users.create.modal.successTitle')
              : t('admin.users.create.modal.title')}
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

        {/* Stage 1: Form */}
        {!invite && (
          <form onSubmit={handleSubmit} className="px-6 py-4 space-y-4">
            {serverError && (
              <div
                role="alert"
                className="text-sm bg-danger-bg text-danger border border-danger/40 rounded px-3 py-2"
              >
                <strong>{t('common.error.prefix')}</strong> {serverError}
              </div>
            )}

            <label className="block">
              <span className="block text-xs font-medium text-fg mb-1">
                {t('admin.users.create.modal.name')} *
              </span>
              <input
                ref={firstInputRef}
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                required
                maxLength={255}
                className="w-full px-3 py-2 text-sm border border-border rounded-md bg-surface text-fg"
              />
              {fieldErrors.name?.map((m) => (
                <span key={m} className="block text-xs text-danger mt-1">
                  {m}
                </span>
              ))}
            </label>

            <label className="block">
              <span className="block text-xs font-medium text-fg mb-1">
                {t('admin.users.create.modal.email')} *
              </span>
              <input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                maxLength={255}
                className="w-full px-3 py-2 text-sm border border-border rounded-md bg-surface text-fg"
              />
              {fieldErrors.email?.map((m) => (
                <span key={m} className="block text-xs text-danger mt-1">
                  {m}
                </span>
              ))}
            </label>

            <fieldset>
              <legend className="block text-xs font-medium text-fg mb-2">
                {t('admin.users.role.label')} *
              </legend>
              <div className="flex flex-col gap-2">
                {(['admin', 'manager', 'viewer'] as UserRole[]).map((r) => (
                  <label key={r} className="inline-flex items-center gap-2 text-sm">
                    <input
                      type="radio"
                      name="new-user-role"
                      value={r}
                      checked={role === r}
                      onChange={() => setRole(r)}
                    />
                    <span>{r}</span>
                  </label>
                ))}
              </div>
            </fieldset>

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
                disabled={create.isPending}
                className="px-4 py-2 text-sm font-medium rounded-md bg-primary text-white hover:bg-primary-hover disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {create.isPending
                  ? t('admin.users.create.modal.submitting')
                  : t('admin.users.create.modal.submit')}
              </button>
            </div>
          </form>
        )}

        {/* Stage 2: Invite sent */}
        {invite && (
          <div className="px-6 py-4 space-y-4">
            <p className="text-sm text-fg">
              ✓ {t('admin.users.create.modal.inviteSentMessage').replace('{email}', invite.email)}
            </p>
            <div className="text-xs text-fg-muted">
              <strong>{t('admin.users.create.modal.inviteExpiresAt')}:</strong>{' '}
              {fmtExpires(invite.expiresAt)}
            </div>
            <div>
              <span className="block text-xs font-medium text-fg mb-1">
                {t('admin.users.create.modal.inviteUrlLabel')}
              </span>
              <div className="flex items-center gap-2">
                <code
                  aria-label={t('admin.users.create.modal.inviteUrlLabel')}
                  className="flex-1 font-mono text-[11px] bg-surface-muted text-fg px-3 py-2 rounded border border-border break-all"
                >
                  {invite.url}
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
