'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useLogout, useMe, usePermissions } from '@/features/auth/api';
import { NotificationsBell } from '@/components/molecules/NotificationsBell/NotificationsBell';

interface NavItem {
  href: string;
  label: string;
  requires?: 'canViewAuditLog';
}

const nav: NavItem[] = [
  { href: '/', label: 'Heatmap' },
  { href: '/timeline', label: 'Timeline' },
  { href: '/members', label: 'Members' },
  { href: '/projects', label: 'Projects' },
  { href: '/allocations', label: 'Allocations' },
  { href: '/audit-logs', label: 'Audit', requires: 'canViewAuditLog' },
];

const ROLE_BADGE_CLASSES: Record<string, string> = {
  admin: 'bg-red-50 text-red-700 border-red-200',
  manager: 'bg-blue-50 text-blue-700 border-blue-200',
  viewer: 'bg-gray-100 text-gray-600 border-gray-300',
};

export function AppHeader() {
  const pathname = usePathname();
  const router = useRouter();
  const { data: me } = useMe();
  const permissions = usePermissions();
  const logout = useLogout();

  const handleLogout = async () => {
    await logout.mutateAsync();
    router.push('/login');
  };

  const visibleNav = nav.filter((n) => {
    if (n.requires === 'canViewAuditLog') return permissions.canViewAuditLog;
    return true;
  });

  return (
    <header className="bg-white border-b border-gray-200">
      <div className="max-w-[1400px] mx-auto px-4 h-14 flex items-center gap-6">
        <Link href="/" className="font-semibold text-gray-900">
          Team Resource
        </Link>
        <nav className="flex items-center gap-1">
          {visibleNav.map((n) => {
            const active = pathname === n.href || (n.href !== '/' && pathname.startsWith(n.href));
            return (
              <Link
                key={n.href}
                href={n.href}
                className={`px-3 py-1.5 text-sm rounded-md transition-colors ${
                  active
                    ? 'bg-blue-50 text-blue-700 font-medium'
                    : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                }`}
              >
                {n.label}
              </Link>
            );
          })}
        </nav>
        <div className="ml-auto flex items-center gap-3">
          {me && (permissions.canWrite || permissions.canViewAuditLog) && <NotificationsBell />}
          {me && (
            <>
              <span
                className={`inline-flex items-center px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider rounded border ${
                  ROLE_BADGE_CLASSES[me.role] ?? ''
                }`}
              >
                {me.role}
              </span>
              <span className="text-sm text-gray-600">
                {me.name} <span className="text-gray-400">({me.email})</span>
              </span>
            </>
          )}
          <button
            onClick={handleLogout}
            disabled={logout.isPending}
            className="px-3 py-1.5 text-xs font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors disabled:opacity-50"
          >
            {logout.isPending ? 'Signing out…' : 'Sign out'}
          </button>
        </div>
      </div>
    </header>
  );
}
