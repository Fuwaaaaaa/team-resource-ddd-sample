import type { UserRole } from '@/features/auth/api';

/**
 * Role chip — used in the AppHeader (top-right) and the /admin/users table.
 * Tokens duplicate AppHeader's previous inline ROLE_BADGE_CLASSES so the
 * single atom is the canonical source after Next 26.
 *
 * a11y: aria-label always carries the textual role for screen readers
 * since color alone is not a sufficient signal.
 */
const ROLE_CLASSES: Record<UserRole, string> = {
  admin:
    'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-800',
  manager:
    'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800',
  viewer: 'bg-surface-muted text-fg-muted border-border',
};

export interface RoleBadgeProps {
  role: UserRole;
  className?: string;
}

export function RoleBadge({ role, className = '' }: RoleBadgeProps) {
  return (
    <span
      role="status"
      aria-label={`role: ${role}`}
      className={`inline-flex items-center px-2 py-0.5 text-xs font-medium rounded border ${ROLE_CLASSES[role]} ${className}`}
    >
      {role}
    </span>
  );
}
