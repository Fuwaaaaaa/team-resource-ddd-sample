import { render, screen, fireEvent, act } from '@testing-library/react';
import { useLocaleStore } from '../store';
import { useTranslation } from '../useTranslation';
import type { TranslationKey } from '../messages';

function Greeter({ keyName, vars }: { keyName: TranslationKey; vars?: Record<string, string | number> }) {
  const t = useTranslation();
  return <div data-testid="out">{t(keyName, vars)}</div>;
}

describe('i18n', () => {
  // 各テスト後に store を初期状態 (ja) に戻す
  afterEach(() => {
    act(() => {
      useLocaleStore.setState({ locale: 'ja' });
    });
  });

  it('デフォルトロケール ja で日本語訳が返る', () => {
    render(<Greeter keyName="nav.members" />);
    expect(screen.getByTestId('out').textContent).toBe('メンバー');
  });

  it('setLocale(en) で英訳に切り替わる', () => {
    const { rerender } = render(<Greeter keyName="nav.members" />);
    act(() => useLocaleStore.getState().setLocale('en'));
    rerender(<Greeter keyName="nav.members" />);
    expect(screen.getByTestId('out').textContent).toBe('Members');
  });

  it('{var} の補間が ja/en 両方で効く', () => {
    render(<Greeter keyName="kpi.projectsCount" vars={{ count: 7 }} />);
    expect(screen.getByTestId('out').textContent).toBe('7 プロジェクト');

    act(() => useLocaleStore.getState().setLocale('en'));
    // re-render trigger via store の pub/sub
    expect(screen.getByTestId('out').textContent).toBe('7 projects');
  });

  it('未サポートロケールは無視される', () => {
    act(() => useLocaleStore.getState().setLocale('xx' as 'en'));
    expect(useLocaleStore.getState().locale).toBe('ja'); // 変わらず
  });

  it('日本語と英語の全キーがペアで存在 (欠損なし)', () => {
    // ランタイムで両辞書のキー集合を比較
    const ja = require('../messages').ja;
    const en = require('../messages').en;
    const jaKeys = Object.keys(ja).sort();
    const enKeys = Object.keys(en).sort();
    expect(enKeys).toEqual(jaKeys);
  });
});
