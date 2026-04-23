'use client';

import { useEffect } from 'react';
import { resolveTheme, useThemeStore } from './store';

/**
 * <html data-theme="..."> を同期させるクライアント専用 bootstrap。
 *
 * - preference 変更時: 即座に反映
 * - preference=system 時: OS の prefers-color-scheme 変化を購読
 * - レンダリングは null (DOM への副作用のみ)
 *
 * SSR 段階では data-theme が付かないため初期ペイントは light テーマ。
 * クライアント hydration 後にストアが読まれて dark が適用される。
 * (FOUC 許容: サンプルなのでスコープ外)
 */
export function ThemeBootstrap(): null {
  const preference = useThemeStore((s) => s.preference);

  useEffect(() => {
    const apply = () => {
      const resolved = resolveTheme(preference);
      document.documentElement.setAttribute('data-theme', resolved);
    };
    apply();

    if (preference === 'system' && typeof window !== 'undefined' && window.matchMedia) {
      const mql = window.matchMedia('(prefers-color-scheme: dark)');
      mql.addEventListener('change', apply);
      return () => mql.removeEventListener('change', apply);
    }
    return undefined;
  }, [preference]);

  return null;
}
