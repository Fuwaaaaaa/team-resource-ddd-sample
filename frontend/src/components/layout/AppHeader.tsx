'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useState } from 'react';
import { useLogout, useMe, usePermissions } from '@/features/auth/api';
import { NotificationsBell } from '@/components/molecules/NotificationsBell/NotificationsBell';
import { useLocaleStore } from '@/lib/i18n/store';
import { useTranslation } from '@/lib/i18n/useTranslation';
import { SUPPORTED_LOCALES, type Locale } from '@/lib/i18n/messages';
import type { TranslationKey } from '@/lib/i18n/messages';
import { useThemeStore, type ThemePreference } from '@/lib/theme/store';

interface NavItem {
  href: string;
  labelKey: TranslationKey;
  requires?: 'canViewAuditLog' | 'canWrite';
}

const nav: NavItem[] = [
  { href: '/', labelKey: 'nav.heatmap' },
  { href: '/timeline', labelKey: 'nav.timeline' },
  { href: '/members', labelKey: 'nav.members' },
  { href: '/projects', labelKey: 'nav.projects' },
  { href: '/allocations', labelKey: 'nav.allocations' },
  { href: '/allocation-requests', labelKey: 'nav.requests', requires: 'canWrite' },
  { href: '/audit-logs', labelKey: 'nav.audit', requires: 'canViewAuditLog' },
];

const ROLE_BADGE_CLASSES: Record<string, string> = {
  admin: 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-800',
  manager: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800',
  viewer: 'bg-surface-muted text-fg-muted border-border',
};

const LOCALE_LABELS: Record<Locale, string> = {
  ja: '日本語',
  en: 'English',
};

export function AppHeader() {
  const pathname = usePathname();
  const router = useRouter();
  const { data: me } = useMe();
  const permissions = usePermissions();
  const logout = useLogout();
  const t = useTranslation();
  const locale = useLocaleStore((s) => s.locale);
  const setLocale = useLocaleStore((s) => s.setLocale);
  const themePreference = useThemeStore((s) => s.preference);
  const setThemePreference = useThemeStore((s) => s.setPreference);
  const [mobileOpen, setMobileOpen] = useState(false);

  const handleLogout = async () => {
    await logout.mutateAsync();
    router.push('/login');
  };

  const visibleNav = nav.filter((n) => {
    if (n.requires === 'canViewAuditLog') return permissions.canViewAuditLog;
    if (n.requires === 'canWrite') return permissions.canWrite;
    return true;
  });

  const navLink = (n: NavItem, onClick?: () => void) => {
    const active = pathname === n.href || (n.href !== '/' && pathname.startsWith(n.href));
    return (
      <Link
        key={n.href}
        href={n.href}
        onClick={onClick}
        className={`px-3 py-1.5 text-sm rounded-md transition-colors ${
          active
            ? 'bg-primary/10 text-primary font-medium'
            : 'text-fg-muted hover:bg-surface-muted hover:text-fg'
        }`}
      >
        {t(n.labelKey)}
      </Link>
    );
  };

  return (
    <header className="bg-surface border-b border-border">
      <div className="max-w-[1400px] mx-auto px-4 h-14 flex items-center gap-3 sm:gap-6">
        {/* モバイル: ハンバーガー */}
        <button
          type="button"
          aria-label={t('header.menu')}
          aria-expanded={mobileOpen}
          onClick={() => setMobileOpen((v) => !v)}
          className="sm:hidden p-2 -ml-2 text-fg-muted hover:text-fg"
        >
          <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            {mobileOpen ? (
              <path d="M4.3 4.3a1 1 0 0 1 1.4 0L10 8.6l4.3-4.3a1 1 0 1 1 1.4 1.4L11.4 10l4.3 4.3a1 1 0 0 1-1.4 1.4L10 11.4l-4.3 4.3a1 1 0 0 1-1.4-1.4L8.6 10 4.3 5.7a1 1 0 0 1 0-1.4Z" />
            ) : (
              <path d="M3 5h14v2H3zM3 9h14v2H3zM3 13h14v2H3z" />
            )}
          </svg>
        </button>

        <Link href="/" className="font-semibold text-fg">
          Team Resource
        </Link>

        {/* デスクトップ nav */}
        <nav className="hidden sm:flex items-center gap-1">
          {visibleNav.map((n) => navLink(n))}
        </nav>

        <div className="ml-auto flex items-center gap-2 sm:gap-3">
          <select
            aria-label={t('header.theme')}
            value={themePreference}
            onChange={(e) => setThemePreference(e.target.value as ThemePreference)}
            className="px-2 py-1 text-xs border border-border rounded-md bg-surface text-fg-muted"
          >
            <option value="light">{t('header.themeLight')}</option>
            <option value="dark">{t('header.themeDark')}</option>
            <option value="system">{t('header.themeSystem')}</option>
          </select>
          <select
            aria-label={t('header.language')}
            value={locale}
            onChange={(e) => setLocale(e.target.value as Locale)}
            className="px-2 py-1 text-xs border border-border rounded-md bg-surface text-fg-muted"
          >
            {SUPPORTED_LOCALES.map((l) => (
              <option key={l} value={l}>
                {LOCALE_LABELS[l]}
              </option>
            ))}
          </select>
          {me && (permissions.canWrite || permissions.canViewAuditLog) && <NotificationsBell />}
          {me && (
            <>
              <span
                className={`hidden sm:inline-flex items-center px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider rounded border ${
                  ROLE_BADGE_CLASSES[me.role] ?? ''
                }`}
              >
                {me.role}
              </span>
              <span className="hidden md:inline text-sm text-fg-muted">
                {me.name} <span className="text-fg-muted/60">({me.email})</span>
              </span>
            </>
          )}
          <button
            onClick={handleLogout}
            disabled={logout.isPending}
            className="px-3 py-1.5 text-xs font-medium text-fg-muted hover:text-fg hover:bg-surface-muted rounded-md border border-border transition-colors disabled:opacity-50"
          >
            {logout.isPending ? t('header.signingOut') : t('header.signOut')}
          </button>
        </div>
      </div>

      {/* モバイル nav オーバーレイ */}
      {mobileOpen && (
        <nav className="sm:hidden border-t border-border bg-surface px-4 py-2 flex flex-col gap-1">
          {visibleNav.map((n) => navLink(n, () => setMobileOpen(false)))}
        </nav>
      )}
    </header>
  );
}
