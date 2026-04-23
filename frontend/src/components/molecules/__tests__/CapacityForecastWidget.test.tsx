import { render, screen, fireEvent } from '@testing-library/react';
import { CapacityForecastWidget } from '../CapacityForecastWidget';
import * as api from '@/features/dashboard/api';

jest.mock('@/features/dashboard/api');

type HookReturn = ReturnType<typeof api.useCapacityForecast>;

function mockHook(value: Partial<HookReturn>): jest.SpyInstance {
  return jest.spyOn(api, 'useCapacityForecast').mockReturnValue(value as HookReturn);
}

describe('CapacityForecastWidget', () => {
  afterEach(() => jest.clearAllMocks());

  it('ローディング時はスケルトンを表示する', () => {
    mockHook({ isLoading: true, isError: false, data: undefined });
    const { container } = render(<CapacityForecastWidget referenceDate="2026-05-01" />);
    expect(container.querySelector('.animate-pulse')).not.toBeNull();
  });

  it('空の場合は「需要データがない」メッセージを表示', () => {
    mockHook({
      isLoading: false,
      isError: false,
      data: { referenceDate: '2026-05-01', monthsAhead: 6, buckets: [] },
    });
    render(<CapacityForecastWidget referenceDate="2026-05-01" />);
    expect(screen.getByText(/需要データがありません/)).toBeInTheDocument();
  });

  it('データを表形式で描画し severity ごとに色分けする', () => {
    mockHook({
      isLoading: false,
      isError: false,
      data: {
        referenceDate: '2026-05-01',
        monthsAhead: 2,
        buckets: [
          {
            month: '2026-05',
            skills: [
              { skillId: 's1', skillName: 'PHP', demandHeadcount: 3, supplyHeadcountEquivalent: 0.5, gap: -2.5, severity: 'critical' },
            ],
          },
          {
            month: '2026-06',
            skills: [
              { skillId: 's1', skillName: 'PHP', demandHeadcount: 3, supplyHeadcountEquivalent: 2.9, gap: -0.1, severity: 'watch' },
            ],
          },
        ],
      },
    });
    render(<CapacityForecastWidget referenceDate="2026-05-01" />);
    expect(screen.getByText('PHP')).toBeInTheDocument();
    expect(screen.getByText('2026-05')).toBeInTheDocument();
    expect(screen.getByText('2026-06')).toBeInTheDocument();
    // gap=-2.5 はマイナス表記、severity=critical で赤背景セル (外側のセル div)
    const criticalCell = screen.getByText('-2.5').parentElement!;
    expect(criticalCell).toHaveClass('bg-red-50');
    // gap=-0.1 は watch で amber
    const watchCell = screen.getByText('-0.1').parentElement!;
    expect(watchCell).toHaveClass('bg-amber-50');
  });

  it('月範囲切替ボタンでhookに新しい期間を渡す', () => {
    const spy = mockHook({ isLoading: true, isError: false, data: undefined });
    render(<CapacityForecastWidget referenceDate="2026-05-01" />);

    // 初期は 6
    expect(spy).toHaveBeenLastCalledWith('2026-05-01', 6);

    fireEvent.click(screen.getByText('3ヶ月'));
    expect(spy).toHaveBeenLastCalledWith('2026-05-01', 3);

    fireEvent.click(screen.getByText('12ヶ月'));
    expect(spy).toHaveBeenLastCalledWith('2026-05-01', 12);
  });
});
