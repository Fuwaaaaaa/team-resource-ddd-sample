import type { Config } from 'jest';
import nextJest from 'next/jest.js';

// Next.js が .env / next.config / SWC トランスフォームを接続してくれるヘルパ
const createJestConfig = nextJest({
  dir: './',
});

const customJestConfig: Config = {
  testEnvironment: 'jsdom',
  setupFilesAfterEnv: ['<rootDir>/jest.setup.ts'],
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/src/$1',
  },
  // e2e/ は Playwright テスト。jest の testMatch は *.spec.ts を拾うので明示的に除外する。
  testPathIgnorePatterns: ['/node_modules/', '/.next/', '/e2e/'],
  coverageDirectory: '.coverage',
  collectCoverageFrom: [
    'src/**/*.{ts,tsx}',
    '!src/**/*.d.ts',
    '!src/**/layout.tsx',
    '!src/**/page.tsx',
  ],
};

export default createJestConfig(customJestConfig);
