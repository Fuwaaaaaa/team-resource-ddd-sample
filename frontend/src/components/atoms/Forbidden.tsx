'use client';

import Link from 'next/link';
import { useTranslation } from '@/lib/i18n/useTranslation';

/**
 * Centered text-only "you don't have access" page. No icon library is used —
 * the project doesn't have one installed and adding one is out of scope.
 */
export function Forbidden() {
  const t = useTranslation();
  return (
    <div className="flex flex-col items-center justify-center text-center py-16 px-4 sm:py-24 sm:px-8 lg:py-32 max-w-prose mx-auto">
      <h2 className="text-2xl font-bold text-fg mb-3">{t('forbidden.title')}</h2>
      <p className="text-sm text-fg-muted mb-8">{t('forbidden.description')}</p>
      <Link
        href="/"
        className="inline-flex items-center justify-center min-h-[40px] px-4 py-2 text-sm font-medium rounded-md bg-primary text-white hover:bg-primary-hover transition-colors"
      >
        {t('forbidden.backHome')}
      </Link>
    </div>
  );
}
