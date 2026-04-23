import '@testing-library/jest-dom';

// recharts の ResponsiveContainer は width/height を親から貰うが jsdom では 0 になる。
// 固定 600x400 のコンテナとして扱うことでチャートがレンダリングされ children の assert が通る。
jest.mock('recharts', () => {
  const actual = jest.requireActual('recharts');
  return {
    ...actual,
    ResponsiveContainer: ({ children }: { children: React.ReactNode }) => children,
  };
});
