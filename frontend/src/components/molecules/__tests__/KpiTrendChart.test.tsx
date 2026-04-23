import { render, screen, fireEvent } from '@testing-library/react';
import { KpiTrendChart } from '../KpiTrendChart';
import * as api from '@/features/dashboard/api';

jest.mock('@/features/dashboard/api');

type HookReturn = ReturnType<typeof api.useKpiTrend>;

function mockHook(value: Partial<HookReturn>): jest.SpyInstance {
  return jest.spyOn(api, 'useKpiTrend').mockReturnValue(value as HookReturn);
}

describe('KpiTrendChart', () => {
  afterEach(() => jest.clearAllMocks());

  it('スナップショット未蓄積のヒントを空状態で表示', () => {
    mockHook({
      isLoading: false,
      isError: false,
      data: { referenceDate: '2026-05-01', days: 30, points: [] },
    });
    render(<KpiTrendChart referenceDate="2026-05-01" />);
    expect(screen.getByText(/スナップショット未蓄積/)).toBeInTheDocument();
  });

  it('日数切替とメトリック切替でhookが呼び直される', () => {
    const spy = mockHook({ isLoading: true, isError: false, data: undefined });
    render(<KpiTrendChart referenceDate="2026-05-01" />);

    // デフォルトは 30 日
    expect(spy).toHaveBeenLastCalledWith('2026-05-01', 30);

    fireEvent.click(screen.getByText('7日'));
    expect(spy).toHaveBeenLastCalledWith('2026-05-01', 7);

    fireEvent.click(screen.getByText('90日'));
    expect(spy).toHaveBeenLastCalledWith('2026-05-01', 90);
  });

  it('エラー時はエラー表示', () => {
    mockHook({ isLoading: false, isError: true, data: undefined });
    render(<KpiTrendChart referenceDate="2026-05-01" />);
    expect(screen.getByText(/取得に失敗しました/)).toBeInTheDocument();
  });
});
