import { render, act } from '@testing-library/react';
import { ThemeBootstrap } from '../ThemeBootstrap';
import { resolveTheme, useThemeStore } from '../store';

describe('theme', () => {
  afterEach(() => {
    act(() => useThemeStore.setState({ preference: 'system' }));
    document.documentElement.removeAttribute('data-theme');
  });

  describe('resolveTheme', () => {
    it('preference=light returns light', () => {
      expect(resolveTheme('light')).toBe('light');
    });

    it('preference=dark returns dark', () => {
      expect(resolveTheme('dark')).toBe('dark');
    });

    it('preference=system follows matchMedia', () => {
      // jsdom は window.matchMedia を定義しないので直接差し込む
      Object.defineProperty(window, 'matchMedia', {
        writable: true,
        configurable: true,
        value: jest.fn().mockReturnValue({
          matches: true,
          media: '(prefers-color-scheme: dark)',
          addEventListener: jest.fn(),
          removeEventListener: jest.fn(),
        }),
      });

      expect(resolveTheme('system')).toBe('dark');

      // 片付け
      Object.defineProperty(window, 'matchMedia', {
        writable: true,
        configurable: true,
        value: undefined,
      });
    });
  });

  describe('ThemeBootstrap', () => {
    it('applies data-theme="dark" when preference=dark', () => {
      act(() => useThemeStore.setState({ preference: 'dark' }));
      render(<ThemeBootstrap />);
      expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    });

    it('applies data-theme="light" when preference=light', () => {
      act(() => useThemeStore.setState({ preference: 'light' }));
      render(<ThemeBootstrap />);
      expect(document.documentElement.getAttribute('data-theme')).toBe('light');
    });

    it('switches data-theme on preference change', () => {
      act(() => useThemeStore.setState({ preference: 'light' }));
      render(<ThemeBootstrap />);
      expect(document.documentElement.getAttribute('data-theme')).toBe('light');

      act(() => useThemeStore.getState().setPreference('dark'));
      expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    });
  });
});
