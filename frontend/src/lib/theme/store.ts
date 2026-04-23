import { create } from 'zustand';
import { persist } from 'zustand/middleware';

export type ThemePreference = 'light' | 'dark' | 'system';
export type ResolvedTheme = 'light' | 'dark';

interface ThemeState {
  preference: ThemePreference;
  setPreference: (pref: ThemePreference) => void;
}

export const useThemeStore = create<ThemeState>()(
  persist(
    (set) => ({
      preference: 'system',
      setPreference: (preference) => set({ preference }),
    }),
    { name: 'theme' },
  ),
);

/**
 * preference + OS preference を解決して実際に適用すべきテーマを返す。
 * SSR / テスト環境で window が無い場合は 'light' にフォールバック。
 */
export function resolveTheme(pref: ThemePreference): ResolvedTheme {
  if (pref !== 'system') return pref;
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
    return 'light';
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}
