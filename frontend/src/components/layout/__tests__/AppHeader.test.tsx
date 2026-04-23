import { render, screen, fireEvent } from '@testing-library/react';
import { AppHeader } from '../AppHeader';
import * as authApi from '@/features/auth/api';

jest.mock('@/features/auth/api');
jest.mock('@/components/molecules/NotificationsBell/NotificationsBell', () => ({
  NotificationsBell: () => null,
}));
jest.mock('next/navigation', () => ({
  usePathname: () => '/',
  useRouter: () => ({ push: jest.fn() }),
}));

function mockMe(role: 'admin' | 'manager' | 'viewer' = 'admin'): void {
  jest.spyOn(authApi, 'useMe').mockReturnValue({
    data: { id: 1, name: 'Admin User', email: 'admin@example.com', role },
  } as unknown as ReturnType<typeof authApi.useMe>);
  jest.spyOn(authApi, 'usePermissions').mockReturnValue({
    role,
    canWrite: role !== 'viewer',
    canViewAuditLog: role === 'admin',
  } as unknown as ReturnType<typeof authApi.usePermissions>);
  jest.spyOn(authApi, 'useLogout').mockReturnValue({
    mutateAsync: jest.fn(),
    isPending: false,
  } as unknown as ReturnType<typeof authApi.useLogout>);
}

describe('AppHeader', () => {
  afterEach(() => {
    jest.clearAllMocks();
    // locale store はテスト間で永続化されるため ja に戻す
    const { useLocaleStore } = require('@/lib/i18n/store');
    useLocaleStore.setState({ locale: 'ja' });
    const { useThemeStore } = require('@/lib/theme/store');
    useThemeStore.setState({ preference: 'system' });
  });

  it('モバイル hamburger はクリック時にメニューを開閉する', () => {
    mockMe();
    render(<AppHeader />);

    const hamburger = screen.getByRole('button', { name: /メニュー/ });
    expect(hamburger).toHaveAttribute('aria-expanded', 'false');

    fireEvent.click(hamburger);
    expect(hamburger).toHaveAttribute('aria-expanded', 'true');

    // デスクトップ nav (hidden sm:flex) と同じリンクがモバイル nav にも表示される
    // 「メンバー」は nav.members のキーで複数箇所にレンダリングされる
    const memberLinks = screen.getAllByText('メンバー');
    expect(memberLinks.length).toBeGreaterThanOrEqual(2);
  });

  it('言語 select で ja ↔ en を切替える', () => {
    mockMe();
    render(<AppHeader />);

    const langSelect = screen.getByLabelText('言語') as HTMLSelectElement;
    expect(langSelect.value).toBe('ja');

    fireEvent.change(langSelect, { target: { value: 'en' } });
    // 再レンダ後、nav の label が英語になる
    expect(screen.getAllByText('Members').length).toBeGreaterThanOrEqual(1);
  });

  it('テーマ select でテーマ設定が変わる', async () => {
    mockMe();
    render(<AppHeader />);
    const themeSelect = screen.getByLabelText('テーマ') as HTMLSelectElement;
    fireEvent.change(themeSelect, { target: { value: 'dark' } });
    // store の状態を直接確認
    const { useThemeStore } = require('@/lib/theme/store');
    expect(useThemeStore.getState().preference).toBe('dark');
  });

  it('viewer ロールは Requests ナビを表示しない', () => {
    mockMe('viewer');
    render(<AppHeader />);
    expect(screen.queryByText('変更申請')).toBeNull();
  });
});
