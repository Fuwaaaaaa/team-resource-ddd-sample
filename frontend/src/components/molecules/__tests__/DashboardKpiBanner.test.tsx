import { render, screen } from '@testing-library/react';
import { DashboardKpiBanner } from '../DashboardKpiBanner';
import * as api from '@/features/dashboard/api';

jest.mock('@/features/dashboard/api');

type UseKpiSummaryReturn = ReturnType<typeof api.useKpiSummary>;

function mockHook(value: Partial<UseKpiSummaryReturn>): void {
  jest.spyOn(api, 'useKpiSummary').mockReturnValue(value as UseKpiSummaryReturn);
}

describe('DashboardKpiBanner', () => {
  afterEach(() => {
    jest.clearAllMocks();
  });

  it('ローディング時はスケルトンを表示する', () => {
    mockHook({ isLoading: true, isError: false, data: undefined });
    render(<DashboardKpiBanner referenceDate="2026-05-01" />);
    // スケルトン (animate-pulse) が 4 つある
    const skeletons = document.querySelectorAll('.animate-pulse');
    expect(skeletons.length).toBe(4);
  });

  it('エラー時はエラーメッセージを表示する', () => {
    mockHook({ isLoading: false, isError: true, data: undefined });
    render(<DashboardKpiBanner referenceDate="2026-05-01" />);
    expect(screen.getByText(/取得に失敗しました/)).toBeInTheDocument();
  });

  it('データ取得後に 4 枚のKPIカードを描画する', () => {
    mockHook({
      isLoading: false,
      isError: false,
      data: {
        referenceDate: '2026-05-01',
        averageFulfillmentRate: 85.5,
        activeProjectCount: 3,
        overloadedMemberCount: 1,
        upcomingEndsThisWeek: 2,
        skillGapsTotal: 4,
      },
    });
    render(<DashboardKpiBanner referenceDate="2026-05-01" />);
    expect(screen.getByText('85.5%')).toBeInTheDocument();
    expect(screen.getByText('3 プロジェクト')).toBeInTheDocument();
    // 過負荷 1 → bad トーンで表示
    expect(screen.getByText('要対処')).toBeInTheDocument();
  });

  it('充足率90%以上はgoodトーン (緑)', () => {
    mockHook({
      isLoading: false,
      isError: false,
      data: {
        referenceDate: '2026-05-01',
        averageFulfillmentRate: 95.0,
        activeProjectCount: 2,
        overloadedMemberCount: 0,
        upcomingEndsThisWeek: 0,
        skillGapsTotal: 0,
      },
    });
    render(<DashboardKpiBanner referenceDate="2026-05-01" />);
    const fulfillmentEl = screen.getByText('95.0%');
    expect(fulfillmentEl).toHaveClass('text-green-700');
  });
});
