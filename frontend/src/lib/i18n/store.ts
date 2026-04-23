import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import { DEFAULT_LOCALE, type Locale, SUPPORTED_LOCALES } from './messages';

interface LocaleState {
  locale: Locale;
  setLocale: (locale: Locale) => void;
}

export const useLocaleStore = create<LocaleState>()(
  persist(
    (set) => ({
      locale: DEFAULT_LOCALE,
      setLocale: (locale) => {
        if (SUPPORTED_LOCALES.includes(locale)) {
          set({ locale });
        }
      },
    }),
    {
      name: 'locale',
      // hydration 時に不正値が入っていたら default に倒す
      onRehydrateStorage: () => (state) => {
        if (state && !SUPPORTED_LOCALES.includes(state.locale)) {
          state.locale = DEFAULT_LOCALE;
        }
      },
    },
  ),
);
