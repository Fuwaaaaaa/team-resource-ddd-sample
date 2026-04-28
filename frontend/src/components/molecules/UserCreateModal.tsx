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

/**
 * Two-stage modal:
 *   stage 1 (form): name / email / role radio
 *   stage 2 (success): displays the generated password ONCE with a copy button
 *
 * The generated password is never re-fetchable; closing or reloading the
 * modal loses it. The warning copy makes that explicit.
 */
export function UserCreateModal({ open, onClose }: UserCreateModalProps) {
  const t = useTranslation();
  const create = useCreateUser();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [role, setRole] = useState<UserRole>('viewer');
  const [serverError, setServerError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [generatedPassword, setGeneratedPassword] = useState<string | null>(null);
  const [createdName, setCreatedName] = useState<string | null>(null);
  const [createdRole, setCreatedRole] = useState<UserRole | null>(null);
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
      setGeneratedPassword(null);
      setCreatedName(null);
      setCreatedRole(null);
      setCopied(false);
      // give the dialog a tick to mount before focusing
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
      setGeneratedPassword(result.generatedPassword);
      setCreatedName(result.user.name);
      setCreatedRole(result.user.role);
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
    if (!generatedPassword) return;
    try {
      await navigator.clipboard.writeText(generatedPassword);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // ignore: clipboard may be unavailable in insecure contexts
    }
  };

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
            {generatedPassword
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
        {!generatedPassword && (
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
                {t('admin.users.create.modal.title')} — {t('admin.users.role.label')} *
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

        {/* Stage 2: Success + generated password */}
        {generatedPassword && (
          <div className="px-6 py-4 space-y-4">
            <p className="text-sm text-fg">
              ✓ {createdName} <span className="text-fg-muted">({createdRole})</span>
            </p>
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
              role="alert"
              className="text-xs bg-warning/10 text-warning border border-warning/40 rounded px-3 py-2"
            >
              <strong>{t('common.error.prefix').replace(':', '')}</strong>{' '}
              {t('admin.users.create.modal.passwordWarning')}
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
