'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useLogout, useMe } from '@/features/auth/api';

const nav = [
  { href: '/', label: 'Heatmap' },
  { href: '/members', label: 'Members' },
  { href: '/projects', label: 'Projects' },
  { href: '/allocations', label: 'Allocations' },
  { href: '/audit-logs', label: 'Audit' },
];

export function AppHeader() {
  const pathname = usePathname();
  const router = useRouter();
  const { data: me } = useMe();
  const logout = useLogout();

  const handleLogout = async () => {
    await logout.mutateAsync();
    router.push('/login');
  };

  return (
    <header className="bg-white border-b border-gray-200">
      <div className="max-w-[1400px] mx-auto px-4 h-14 flex items-center gap-6">
        <Link href="/" className="font-semibold text-gray-900">
          Team Resource
        </Link>
        <nav className="flex items-center gap-1">
          {nav.map((n) => {
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
          {me && (
            <span className="text-sm text-gray-600">
              {me.name} <span className="text-gray-400">({me.email})</span>
            </span>
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
